<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

/**
 * Dispatched in `SessionController::login()` whenever a login attempt fails, whether at form
 * validation (`reason` `'validation_failed'`) or post-validation (`'user_not_found'`,
 * `'invalid_password'`, `'account_blocked'`), carrying the submitted login identifier (`null` when it
 * can't be derived, e.g. an empty field) and the request's server params. Not cancellable - the login
 * has already failed by the time this fires.
 */
final readonly class FailedLoginEvent
{
    /**
     * @param array<array-key, mixed> $serverParams
     */
    public function __construct(
        private ?string $email,
        private string $reason,
        private array $serverParams = [],
    ) {}

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }
}
