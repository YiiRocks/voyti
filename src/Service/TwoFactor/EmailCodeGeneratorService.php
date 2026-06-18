<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\TwoFactor;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Helper\SecurityHelper;
use YiiRocks\Voyti\Service\MailService;

final class EmailCodeGeneratorService
{
    public function __construct(
        private readonly SecurityHelper $securityHelper,
        private readonly MailService $mailService,
    ) {
    }

    public function run(User $user): string
    {
        $code = (string)random_int(100000, 999999);

        $user->setAuthTfKey($code);
        $user->save();

        $this->mailService->sendTwoFactorCode($user->getEmail(), $code);

        return $code;
    }
}
