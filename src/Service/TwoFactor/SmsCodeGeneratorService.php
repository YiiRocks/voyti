<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\TwoFactor;

use YiiRocks\Voyti\Entity\User;

final class SmsCodeGeneratorService
{


    public function run(User $user): string
    {
        $code = (string)random_int(100000, 999999);

        $user->setAuthTfKey($code);
        $user->save();

        return $code;
    }
}
