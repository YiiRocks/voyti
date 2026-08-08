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
        /** @infection-ignore-all The exact random bounds can't be pinned by a deterministic test (a single draw rarely lands on a boundary); an off-by-one on either end still yields an acceptable numeric code. */
        $code = (string) random_int(100000, 999999);

        $user->setAuthTfKey($code);
        $user->save();

        $this->mailService->sendTwoFactorCode($user->getEmail(), $code);

        return $code;
    }
}
