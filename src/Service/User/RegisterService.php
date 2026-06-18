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

final class RegisterService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailService $mailService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SecurityHelper $securityHelper,
        private readonly ModuleConfig $config,
    ) {
    }

    public function run(array $data): ServiceResult
    {
        $password = $data['password'] ?? $this->securityHelper->generateRandomString(12);
        $gdprConsent = (bool)($data['gdprConsent'] ?? false);

        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setPasswordHash($this->securityHelper->hashPassword($password, $this->config->blowfishCost));
        $user->setAuthKey($this->securityHelper->generateRandomString());
        $user->setRegistrationIp(
            $this->config->disableIpLogging ? '127.0.0.1' : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')
        );
        $user->setGdprConsent($this->config->enableGdprCompliance ? $gdprConsent : false);
        if ($gdprConsent) {
            $user->setGdprConsentDate(time());
        }
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());

        $profile = new Profile();
        $profile->setUserId(null);

        $this->eventDispatcher->dispatch(new UserEvent($user));

        if ($this->config->enableEmailConfirmation) {
            $token = new Token();
            $token->setType(Token::TYPE_CONFIRMATION);
            $token->setCreatedAt(time());
            $token->setCode($this->securityHelper->generateRandomString(32));

            $this->userRepository->saveWithProfileAndToken($user, $profile, $token);
            $this->mailService->sendConfirmation($user, $token);

            $this->eventDispatcher->dispatch(new AfterRegisterEvent($user));
            return ServiceResult::success('account_created_check_email');
        }

        $user->setConfirmedAt(time());
        $this->userRepository->saveWithProfile($user, $profile);
        $this->mailService->sendWelcome($user, $password);

        $this->eventDispatcher->dispatch(new AfterRegisterEvent($user));
        return ServiceResult::success('account_created');
    }
}
