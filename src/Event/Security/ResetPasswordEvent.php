<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Security;

use YiiRocks\Voyti\Model\UserToken;

/**
 * Dispatched when a password-recovery token is issued, carrying the `UserToken` so listeners
 * (e.g. mail sending) can build a reset link from its code.
 */
final readonly class ResetPasswordEvent
{
    public function __construct(
        private UserToken $userToken,
    ) {}

    public function getToken(): UserToken
    {
        return $this->userToken;
    }
}
