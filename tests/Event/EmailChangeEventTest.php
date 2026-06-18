<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YiiRocks\Voyti\Event\EmailChangeEvent;

final class EmailChangeEventTest extends TestCase
{
    public function testConstructAndGetters(): void
    {
        $user = (new ReflectionClass(\YiiRocks\Voyti\Entity\User::class))->newInstanceWithoutConstructor();
        $newEmail = 'new@example.com';
        $event = new EmailChangeEvent($user, $newEmail);

        $this->assertSame($user, $event->getUser());
        $this->assertSame($newEmail, $event->getNewEmail());
    }
}
