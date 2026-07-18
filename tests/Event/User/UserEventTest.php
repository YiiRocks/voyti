<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event\User;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;

final class UserEventTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $user = new User();

        $event = new UserEvent($user, UserEvent::BLOCK);

        self::assertSame($user, $event->getUser());
        self::assertSame(UserEvent::BLOCK, $event->getType());
    }
}
