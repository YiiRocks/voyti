<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Session;

/**
 * Dispatched when a user session is created, terminated, or updated, carrying the user and
 * session id plus a `type` entry in `$data` set to one of the `SESSION_*` constants.
 */
final readonly class SessionEvent
{
    public const string SESSION_CREATED = 'sessionCreated';
    public const string SESSION_TERMINATED = 'sessionTerminated';
    public const string SESSION_UPDATED = 'sessionUpdated';

    public function __construct(
        private int $userId,
        private string $sessionId,
        private array $data = [],
    ) {}

    public function getData(): array
    {
        return $this->data;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }
}
