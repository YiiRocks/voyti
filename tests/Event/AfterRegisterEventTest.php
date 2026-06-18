<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YiiRocks\Voyti\Event\AfterRegisterEvent;

final class AfterRegisterEventTest extends TestCase
{
    public function testConstructAndGetters(): void
    {
        $user = (new ReflectionClass(\YiiRocks\Voyti\Entity\User::class))->newInstanceWithoutConstructor();
        $event = new AfterRegisterEvent($user);

        $this->assertSame($user, $event->getUser());
    }
}
