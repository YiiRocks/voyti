<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Session;

final class SessionEvent
{
    public const SESSION_CREATED = 'sessionCreated';
    public const SESSION_TERMINATED = 'sessionTerminated';
    public const SESSION_UPDATED = 'sessionUpdated';

    public function __construct(
        private readonly int $userId,
        private readonly string $sessionId,
        private readonly array $data = [],
    ) {
    }

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
