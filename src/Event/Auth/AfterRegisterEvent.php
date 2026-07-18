<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

use YiiRocks\Voyti\Model\User;

/**
 * Dispatched after a new user account is created, carrying the newly registered `User`.
 */
final readonly class AfterRegisterEvent
{
    public function __construct(
        private User $user,
    ) {}

    public function getUser(): User
    {
        return $this->user;
    }
}
