<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YiiRocks\Voyti\Event\AfterLoginEvent;
use Yiisoft\Router\CurrentRoute;

final class AfterLoginEventTest extends TestCase
{
    private static function createUser(): object
    {
        return (new ReflectionClass(\YiiRocks\Voyti\Entity\User::class))->newInstanceWithoutConstructor();
    }

    private static function createRoute(): object
    {
        return (new ReflectionClass(CurrentRoute::class))->newInstanceWithoutConstructor();
    }

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
}
