<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YiiRocks\Voyti\Event\ProfileEvent;

final class ProfileEventTest extends TestCase
{
    public function testConstructAndGetters(): void
    {
        $profile = (new ReflectionClass(\YiiRocks\Voyti\Entity\Profile::class))->newInstanceWithoutConstructor();
        $event = new ProfileEvent($profile);

        $this->assertSame($profile, $event->getProfile());
    }
}
