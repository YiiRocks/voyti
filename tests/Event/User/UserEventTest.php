<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event\User;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;

final class UserEventTest extends TestCase
{

    public function testConstants(): void
    {
        self::assertSame('afterAccountUpdate', UserEvent::AFTER_ACCOUNT_UPDATE);
        self::assertSame('afterBlock', UserEvent::AFTER_BLOCK);
        self::assertSame('afterConfirmation', UserEvent::AFTER_CONFIRMATION);
        self::assertSame('afterCreate', UserEvent::AFTER_CREATE);
        self::assertSame('afterDelete', UserEvent::AFTER_DELETE);
        self::assertSame('afterLogout', UserEvent::AFTER_LOGOUT);
        self::assertSame('afterProfileUpdate', UserEvent::AFTER_PROFILE_UPDATE);
        self::assertSame('afterSwitchIdentity', UserEvent::AFTER_SWITCH_IDENTITY);
        self::assertSame('afterUnblock', UserEvent::AFTER_UNBLOCK);
        self::assertSame('beforeAccountUpdate', UserEvent::BEFORE_ACCOUNT_UPDATE);
        self::assertSame('beforeBlock', UserEvent::BEFORE_BLOCK);
        self::assertSame('beforeConfirmation', UserEvent::BEFORE_CONFIRMATION);
        self::assertSame('beforeCreate', UserEvent::BEFORE_CREATE);
        self::assertSame('beforeDelete', UserEvent::BEFORE_DELETE);
        self::assertSame('beforeLogout', UserEvent::BEFORE_LOGOUT);
        self::assertSame('beforeProfileUpdate', UserEvent::BEFORE_PROFILE_UPDATE);
        self::assertSame('beforeSwitchIdentity', UserEvent::BEFORE_SWITCH_IDENTITY);
        self::assertSame('beforeUnblock', UserEvent::BEFORE_UNBLOCK);
    }
    public function testConstructorAndGetters(): void
    {
        $user = new User();

        $event = new UserEvent($user);

        self::assertSame($user, $event->getUser());
    }
}
