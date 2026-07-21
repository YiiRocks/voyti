<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Clock;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * Default {@see ClockInterface} implementation, backed by the system clock.
 */
final class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
