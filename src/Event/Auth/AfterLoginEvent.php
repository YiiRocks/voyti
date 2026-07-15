<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

use YiiRocks\Voyti\Model\User;

final readonly class AfterLoginEvent
{
    public function __construct(
        private User $user,
        private ?string $previousSessionId = null,
    ) {
    }

    public function getPreviousSessionId(): ?string
    {
        return $this->previousSessionId;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
