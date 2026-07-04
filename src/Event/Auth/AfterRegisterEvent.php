<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

use YiiRocks\Voyti\Entity\User;

final readonly class AfterRegisterEvent
{
    public function __construct(
        private User $user,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
