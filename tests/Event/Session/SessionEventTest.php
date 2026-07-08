<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event\Session;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\Session\SessionEvent;

final class SessionEventTest extends TestCase
{

    public function testConstants(): void
    {
        self::assertSame('sessionCreated', SessionEvent::SESSION_CREATED);
        self::assertSame('sessionTerminated', SessionEvent::SESSION_TERMINATED);
        self::assertSame('sessionUpdated', SessionEvent::SESSION_UPDATED);
    }
    public function testConstructorAndGetters(): void
    {
        $event = new SessionEvent(42, 'session-abc-123', ['key' => 'value']);

        self::assertSame(42, $event->getUserId());
        self::assertSame('session-abc-123', $event->getSessionId());
        self::assertSame(['key' => 'value'], $event->getData());
    }

    public function testConstructorWithDefaultData(): void
    {
        $event = new SessionEvent(1, 'sess-id');

        self::assertSame(1, $event->getUserId());
        self::assertSame('sess-id', $event->getSessionId());
        self::assertSame([], $event->getData());
    }
}
