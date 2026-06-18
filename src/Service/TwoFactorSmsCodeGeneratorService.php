<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Helper\SecurityHelper;

final class TwoFactorSmsCodeGeneratorService
{
    public function __construct(
        private readonly SecurityHelper $securityHelper,
    ) {
    }

    public function run(User $user): string
    {
        $code = (string)random_int(100000, 999999);

        $user->setAuthTfKey($code);
        $user->save();

        return $code;
    }
}
