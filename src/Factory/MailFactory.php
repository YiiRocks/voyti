<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Factory;

use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Service\MailService;

final class MailFactory
{
    public function __construct(
        private readonly MailService $mailService,
    ) {
    }

    public function getMailService(): MailService
    {
        return $this->mailService;
    }

    public function sendConfirmation(User $user, UserToken $userToken): bool
    {
        return $this->mailService->sendConfirmation($user, $userToken);
    }

    public function sendReconfirmation(User $user, UserToken $userToken): bool
    {
        return $this->mailService->sendReconfirmation($user, $userToken);
    }

    public function sendRecovery(string $email, UserToken $userToken): bool
    {
        return $this->mailService->sendRecovery($email, $userToken);
    }

    public function sendTwoFactorCode(string $email, string $code): bool
    {
        return $this->mailService->sendTwoFactorCode($email, $code);
    }

    public function sendWelcome(User $user, string $password = ''): bool
    {
        return $this->mailService->sendWelcome($user, $password);
    }
}
