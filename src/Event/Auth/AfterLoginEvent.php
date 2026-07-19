<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

use YiiRocks\Voyti\Model\User;

/**
 * Dispatched after a user successfully authenticates (password login, two-factor confirmation,
 * or social login), carrying the logged-in `User`, the request's server params (for listeners
 * that need the remote IP / user agent), and, when the session was rotated as part of login, the
 * previous session id so listeners can migrate session-scoped data.
 */
final readonly class AfterLoginEvent
{
    /**
     * @param array<array-key, mixed> $serverParams
     */
    public function __construct(
        private User $user,
        private ?string $previousSessionId = null,
        private array $serverParams = [],
    ) {}

    public function getPreviousSessionId(): ?string
    {
        return $this->previousSessionId;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
