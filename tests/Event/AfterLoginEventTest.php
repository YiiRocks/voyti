<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use Yiisoft\Router\CurrentRoute;

final class AfterLoginEventTest extends TestCase
{

    public function testConstructAndGettersWithRoute(): void
    {
        $user = self::createUser();
        $route = self::createRoute();
        $event = new AfterLoginEvent($user, $route);

        $this->assertSame($user, $event->getUser());
        $this->assertSame($route, $event->getRoute());
    }

    public function testConstructWithNullRoute(): void
    {
        $user = self::createUser();
        $event = new AfterLoginEvent($user);

        $this->assertSame($user, $event->getUser());
        $this->assertNull($event->getRoute());
    }

    private static function createRoute(): CurrentRoute
    {
        return (new ReflectionClass(CurrentRoute::class))->newInstanceWithoutConstructor();
    }
    private static function createUser(): User
    {
        return (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
    }
}
