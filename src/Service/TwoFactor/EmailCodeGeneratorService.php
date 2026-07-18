<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\TwoFactor;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\MailService;

/**
 * Generates a random six-digit email two-factor authentication code, stores it on the user, and
 * emails it via {@see MailService}.
 */
final readonly class EmailCodeGeneratorService
{
    public function __construct(
        private MailService $mailService,
    ) {}

    /**
     * @return numeric-string
     */
    public function run(User $user): string
    {
        $code = (string) random_int(100000, 999999);

        $user->setAuthTfKey($code);
        $user->save();

        $this->mailService->sendTwoFactorCode($user->getEmail(), $code);

        return $code;
    }
}
