<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event\Gdpr;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\Gdpr\GdprEvent;
use YiiRocks\Voyti\Model\User;

final class GdprEventTest extends TestCase
{

    public function testConstructorAndGetters(): void
    {
        $user = new User();

        $event = new GdprEvent($user);

        self::assertSame($user, $event->getUser());
        self::assertTrue($event->isValid);
    }

    public function testIsValidMutable(): void
    {
        $user = new User();
        $event = new GdprEvent($user);

        self::assertTrue($event->isValid);
        $event->isValid = false;
        self::assertFalse($event->isValid);
    }
}
