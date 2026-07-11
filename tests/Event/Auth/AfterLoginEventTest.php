<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event\Auth;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Model\User;

final class AfterLoginEventTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $user = new User();

        $event = new AfterLoginEvent($user);

        self::assertSame($user, $event->getUser());
        self::assertNull($event->getRoute());
    }

    public function testConstructorWithNullRoute(): void
    {
        $user = new User();

        $event = new AfterLoginEvent($user);

        self::assertSame($user, $event->getUser());
        self::assertNull($event->getRoute());
    }
}
