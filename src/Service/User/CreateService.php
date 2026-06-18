<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\Profile;
use YiiRocks\Voyti\Entity\Token;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Helper\SecurityHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\ServiceResult;

final class CreateService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailService $mailService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SecurityHelper $securityHelper,
        private readonly ModuleConfig $config,
    ) {
    }

    public function run(string $email, string $username, string $password): ServiceResult
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($this->securityHelper->hashPassword($password, $this->config->blowfishCost));
        $user->setAuthKey($this->securityHelper->generateRandomString());
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());

        $this->eventDispatcher->dispatch(new UserEvent($user));

        $profile = new Profile();
        $profile->setUserId(null);

        if ($this->config->enableEmailConfirmation) {
            $token = new Token();
            $token->setType(Token::TYPE_CONFIRMATION);
            $token->setCreatedAt(time());
            $token->setCode($this->securityHelper->generateRandomString(32));

            $this->userRepository->saveWithProfileAndToken($user, $profile, $token);
            $this->mailService->sendConfirmation($user, $token);
        } else {
            $user->setConfirmedAt(time());
            $this->userRepository->saveWithProfile($user, $profile);
            $this->mailService->sendWelcome($user, $password);
        }

        $this->eventDispatcher->dispatch(new AfterRegisterEvent($user));
        return ServiceResult::success('User has been created');
    }
}
