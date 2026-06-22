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

final class CreateService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailService $mailService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PasswordHasher $passwordHasher,
        private readonly ModuleConfig $config,
    ) {
    }

    public function run(string $email, string $username, string $password): ServiceResult
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($this->passwordHasher->hash($password));
        $user->setAuthKey(Random::string());
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());

        $this->eventDispatcher->dispatch(new UserEvent($user));

        $userProfile = new UserProfile();

        if ($this->config->enableEmailConfirmation) {
            $userToken = new UserToken();
            $userToken->setType(UserToken::TYPE_CONFIRMATION);
            $userToken->setCreatedAt(time());
            $userToken->setCode(Random::string(32));

            $this->userRepository->saveWithProfileAndToken($user, $userProfile, $userToken);
            $this->mailService->sendConfirmation($user, $userToken);
        } else {
            $user->setConfirmedAt(time());
            $this->userRepository->saveWithProfile($user, $userProfile);
            $this->mailService->sendWelcome($user, $password);
        }

        $this->eventDispatcher->dispatch(new AfterRegisterEvent($user));
        return ServiceResult::success('User has been created');
    }
}
