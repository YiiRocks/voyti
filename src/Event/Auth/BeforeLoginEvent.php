<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Model\User;

/**
 * Dispatched by `LoginCompletionService::checkBeforeLogin()` before the password is checked. `$user`
 * is null when the submitted username didn't resolve to an account. Cancellable: a listener may throw
 * {@see ActionPreventedException} to prevent the login.
 */
final readonly class BeforeLoginEvent
{
    /**
     * @param array<array-key, mixed> $serverParams
     */
    public function __construct(
        private ?User $user,
        private array $serverParams = [],
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
