<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\SessionEvent;

final class SessionEventTest extends TestCase
{
    public function testConstructAndGettersWithData(): void
    {
        $data = ['ip' => '127.0.0.1', 'ua' => 'Test'];
        $event = new SessionEvent(42, 'abc123', $data);

        $this->assertSame(42, $event->getUserId());
        $this->assertSame('abc123', $event->getSessionId());
        $this->assertSame($data, $event->getData());
    }

    public function testConstructWithDefaultData(): void
    {
        $event = new SessionEvent(1, 'sid');

        $this->assertSame(1, $event->getUserId());
        $this->assertSame('sid', $event->getSessionId());
        $this->assertSame([], $event->getData());
    }
}
