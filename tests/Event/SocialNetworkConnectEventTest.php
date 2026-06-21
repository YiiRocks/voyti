<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YiiRocks\Voyti\Event\Auth\UserSocialConnectEvent;

final class UserSocialConnectEventTest extends TestCase
{
    public function testConstructAndGetters(): void
    {
        $user = (new ReflectionClass(\YiiRocks\Voyti\Entity\User::class))->newInstanceWithoutConstructor();
        $account = (new ReflectionClass(\YiiRocks\Voyti\Entity\UserSocialAccount::class))->newInstanceWithoutConstructor();
        $event = new UserSocialConnectEvent($user, $account);

        $this->assertSame($user, $event->getUser());
        $this->assertSame($account, $event->getAccount());
    }
}
