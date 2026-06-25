<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YiiRocks\Voyti\Event\User\UserProfileEvent;

final class UserProfileEventTest extends TestCase
{
    public function testConstructAndGetters(): void
    {
        $userProfile = (new ReflectionClass(\YiiRocks\Voyti\Entity\UserProfile::class))->newInstanceWithoutConstructor();
        $event = new UserProfileEvent($userProfile);

        $this->assertSame($userProfile, $event->getProfile());
    }
}
