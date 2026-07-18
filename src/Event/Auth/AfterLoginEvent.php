<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

use YiiRocks\Voyti\Model\User;

/**
 * Dispatched after a user successfully authenticates (password login, two-factor confirmation,
 * or social login), carrying the logged-in `User` and, when the session was rotated as part of
 * login, the previous session id so listeners can migrate session-scoped data.
 */
final readonly class AfterLoginEvent
{
    public function __construct(
        private User $user,
        private ?string $previousSessionId = null,
    ) {}

    public function getPreviousSessionId(): ?string
    {
        return $this->previousSessionId;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
