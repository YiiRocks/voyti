<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\ServiceResult;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;

final readonly class RegisterService
{
    public function __construct(
        private UserRepository $userRepository,
        private MailService $mailService,
        private EventDispatcherInterface $eventDispatcher,
        private PasswordHasher $passwordHasher,
        private ModuleConfig $config,
        private PasswordGeneratorInterface $passwordGenerator,
    ) {
    }

    public function run(array $data): ServiceResult
    {
        $username = isset($data['username']) && is_string($data['username']) ? $data['username'] : '';
        $email = isset($data['email']) && is_string($data['email']) ? $data['email'] : '';
        $password = isset($data['password']) && is_string($data['password']) && $data['password'] !== ''
            ? $data['password']
            : $this->passwordGenerator->generate(12);
        $gdprConsent = (bool) ($data['gdprConsent'] ?? false);

        if ($this->userRepository->findByEmail($email) !== null) {
            return ServiceResult::failure('Email already exists', ['Email already exists']);
        }

        if ($this->userRepository->findByUsername($username) !== null) {
            return ServiceResult::failure('Username already exists', ['Username already exists']);
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($this->passwordHasher->hash($password));
        $user->setAuthKey(Random::string());
        $user->setRegistrationIp(
            $this->config->disableIpLogging ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')
        );
        $user->setGdprConsent($this->config->enableGdprCompliance ? $gdprConsent : false);
        if ($this->config->enableGdprCompliance && $gdprConsent) {
            $user->setGdprConsentDate(time());
        }
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());

        $userProfile = new UserProfile();

        $this->eventDispatcher->dispatch(new UserEvent($user));

        if ($this->config->enableEmailConfirmation) {
            $userToken = new UserToken();
            $userToken->setCreatedAt(time());
            $userToken->setCode(Random::string(32));

            $this->userRepository->saveWithProfileAndToken($user, $userProfile, $userToken);
            $this->mailService->sendConfirmation($user, $userToken);

            $this->eventDispatcher->dispatch(new AfterRegisterEvent($user));
            return ServiceResult::success('voyti.registration.account_created_check_email');
        }

        $user->setConfirmedAt(time());
        $this->userRepository->saveWithProfile($user, $userProfile);
        $this->mailService->sendWelcome($user, $password);

        $this->eventDispatcher->dispatch(new AfterRegisterEvent($user));
        return ServiceResult::success('voyti.registration.account_created');
    }
}
