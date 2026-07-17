<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Security;

use YiiRocks\Voyti\Model\UserToken;

final readonly class ResetPasswordEvent
{
    public function __construct(
        private UserToken $userToken,
    ) {
    }

    public function getToken(): UserToken
    {
        return $this->userToken;
    }
}
