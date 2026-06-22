<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\ServiceResult;

final class RegisterService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailService $mailService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PasswordHasher $passwordHasher,
        private readonly ModuleConfig $config,
    ) {
    }

    public function run(array $data): ServiceResult
    {
        $password = $data['password'] ?? Random::string(12);
        $gdprConsent = (bool)($data['gdprConsent'] ?? false);

        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setPasswordHash($this->passwordHasher->hash($password));
        $user->setAuthKey(Random::string());
        $user->setRegistrationIp(
            $this->config->disableIpLogging ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')
        );
        $user->setGdprConsent($this->config->enableGdprCompliance ? $gdprConsent : false);
        if ($gdprConsent) {
            $user->setGdprConsentDate(time());
        }
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());

        $userProfile = new UserProfile();

        $this->eventDispatcher->dispatch(new UserEvent($user));

        if ($this->config->enableEmailConfirmation) {
            $userToken = new UserToken();
            $userToken->setType(UserToken::TYPE_CONFIRMATION);
            $userToken->setCreatedAt(time());
            $userToken->setCode(Random::string(32));

            $this->userRepository->saveWithProfileAndToken($user, $userProfile, $userToken);
            $this->mailService->sendConfirmation($user, $userToken);

            $this->eventDispatcher->dispatch(new AfterRegisterEvent($user));
            return ServiceResult::success('account_created_check_email');
        }

        $user->setConfirmedAt(time());
        $this->userRepository->saveWithProfile($user, $userProfile);
        $this->mailService->sendWelcome($user, $password);

        $this->eventDispatcher->dispatch(new AfterRegisterEvent($user));
        return ServiceResult::success('account_created');
    }
}
