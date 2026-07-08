<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event\User;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Event\User\UserProfileEvent;

final class UserProfileEventTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $profile = new UserProfile();

        $event = new UserProfileEvent($profile);

        self::assertSame($profile, $event->getProfile());
    }
}
