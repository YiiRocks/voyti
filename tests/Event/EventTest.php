<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Event;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\Event\Security\ResetPasswordEvent;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Event\User\UserProfileEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserToken;

final class EventTest extends TestCase
{
    public function testAfterLoginEventConstructorAndGetters(): void
    {
        $user = new User();

        $event = new AfterLoginEvent($user);

        self::assertSame($user, $event->getUser());
    }

    public function testAfterRegisterEventConstructorAndGetters(): void
    {
        $user = new User();

        $event = new AfterRegisterEvent($user);

        self::assertSame($user, $event->getUser());
    }

    public function testResetPasswordEventConstructorAndGetters(): void
    {
        $token = new UserToken();
        $token->setCode('abc');
        $token->setUserId(1);
        $token->setType(0);
        $token->setCreatedAt(1000);

        $event = new ResetPasswordEvent($token);

        self::assertSame($token, $event->getToken());
    }

    public function testSessionEventConstructorAndGetters(): void
    {
        $event = new SessionEvent(42, 'session-abc-123', ['key' => 'value']);

        self::assertSame(42, $event->getUserId());
        self::assertSame('session-abc-123', $event->getSessionId());
        self::assertSame(['key' => 'value'], $event->getData());
    }

    public function testUserEventConstructorAndGetters(): void
    {
        $user = new User();

        $event = new UserEvent($user, UserEvent::BLOCK);

        self::assertSame($user, $event->getUser());
        self::assertSame(UserEvent::BLOCK, $event->getType());
    }

    public function testUserProfileEventConstructorAndGetters(): void
    {
        $profile = new UserProfile();

        $event = new UserProfileEvent($profile);

        self::assertSame($profile, $event->getProfile());
    }
}
