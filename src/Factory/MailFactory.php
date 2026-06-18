<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Factory;

use YiiRocks\Voyti\Entity\Token;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Service\MailService;

final class MailFactory
{
    public function __construct(
        private readonly MailService $mailService,
    ) {
    }

    public function sendWelcome(User $user, string $password = ''): bool
    {
        return $this->mailService->sendWelcome($user, $password);
    }

    public function sendConfirmation(User $user, Token $token): bool
    {
        return $this->mailService->sendConfirmation($user, $token);
    }

    public function sendRecovery(string $email, Token $token): bool
    {
        return $this->mailService->sendRecovery($email, $token);
    }

    public function sendReconfirmation(User $user, Token $token): bool
    {
        return $this->mailService->sendReconfirmation($user, $token);
    }

    public function sendTwoFactorCode(string $email, string $code): bool
    {
        return $this->mailService->sendTwoFactorCode($email, $code);
    }

    public function getMailService(): MailService
    {
        return $this->mailService;
    }
}
