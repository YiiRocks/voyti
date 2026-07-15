<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\User;

use YiiRocks\Voyti\Model\User;

final readonly class UserEvent
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
