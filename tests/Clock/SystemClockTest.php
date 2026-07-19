<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Clock;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Clock\SystemClock;

final class SystemClockTest extends TestCase
{
    public function testNowReturnsCurrentTime(): void
    {
        $before = time();
        $now = (new SystemClock())->now();
        $after = time();

        self::assertGreaterThanOrEqual($before, $now->getTimestamp());
        self::assertLessThanOrEqual($after, $now->getTimestamp());
    }
}
