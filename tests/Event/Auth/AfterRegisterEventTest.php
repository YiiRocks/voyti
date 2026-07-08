<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event\Auth;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;

final class AfterRegisterEventTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $user = new User();

        $event = new AfterRegisterEvent($user);

        self::assertSame($user, $event->getUser());
    }
}
