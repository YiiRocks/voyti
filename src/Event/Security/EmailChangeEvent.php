<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Security;

use YiiRocks\Voyti\Entity\User;

final readonly class EmailChangeEvent
{
    public function __construct(
        private User $user,
        private string $newEmail,
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
