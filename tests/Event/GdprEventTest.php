<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YiiRocks\Voyti\Event\GdprEvent;

final class GdprEventTest extends TestCase
{
    public function testConstructAndGetters(): void
    {
        $user = (new ReflectionClass(\YiiRocks\Voyti\Entity\User::class))->newInstanceWithoutConstructor();
        $event = new GdprEvent($user);

        $this->assertSame($user, $event->getUser());
    }

    public function testIsValidDefaultTrue(): void
    {
        $user = (new ReflectionClass(\YiiRocks\Voyti\Entity\User::class))->newInstanceWithoutConstructor();
        $event = new GdprEvent($user);

        $this->assertTrue($event->isValid);
    }

    public function testIsValidCanBeSetFalse(): void
    {
        $user = (new ReflectionClass(\YiiRocks\Voyti\Entity\User::class))->newInstanceWithoutConstructor();
        $event = new GdprEvent($user);
        $event->isValid = false;

        $this->assertFalse($event->isValid);
    }
}
