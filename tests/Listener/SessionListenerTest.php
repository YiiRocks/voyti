<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Listener;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Listener\SessionListener;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\UserSession\UserSessionDecorator;

#[AllowMockObjectsWithoutExpectations]
final class SessionListenerTest extends TestCase
{
    public function testOnAfterLoginPassesPreviousSessionIdThrough(): void
    {
        $decorator = $this->createMock(UserSessionDecorator::class);
        $decorator->expects(self::once())->method('registerLogin')->with(
            self::isInstanceOf(User::class),
            'old-session-id',
        );

        $listener = new SessionListener($decorator);
        $user = new User();
        $event = new AfterLoginEvent($user, previousSessionId: 'old-session-id');

        $listener->onAfterLogin($event);
    }

    public function testOnAfterLoginRecordsSession(): void
    {
        $decorator = $this->createMock(UserSessionDecorator::class);
        $decorator->expects(self::once())->method('registerLogin')->with(
            self::isInstanceOf(User::class),
            null,
        );

        $listener = new SessionListener($decorator);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);
    }
}
