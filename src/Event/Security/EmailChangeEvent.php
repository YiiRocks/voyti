<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Security;

use YiiRocks\Voyti\Entity\User;

final class EmailChangeEvent
{
    public function __construct(
        private readonly User $user,
        private readonly string $newEmail,
    ) {
    }

    public function getNewEmail(): string
    {
        return $this->newEmail;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
