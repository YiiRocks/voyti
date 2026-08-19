<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\User;

/**
 * Dispatched in `SessionController::logout()` when a user intentionally logs out, carrying the `User`
 * and the terminated session id. Fired alongside (not instead of) {@see SessionEvent} with
 * `SESSION_TERMINATED`, distinguishing an intentional logout from passive session termination
 * (timeout, admin revocation) for listeners such as OAuth2/SSO revocation hooks.
 */
final readonly class LogoutEvent
{
    public function __construct(
        private User $user,
        private string $sessionId,
    ) {}

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
