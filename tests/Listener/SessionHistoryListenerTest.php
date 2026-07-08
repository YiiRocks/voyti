<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Listener;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Listener\SessionHistoryListener;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\UserSessionHistory\UserSessionHistoryDecorator;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class SessionHistoryListenerTest extends TestCase
{

    public function testOnAfterLoginDoesNothingWhenDisabled(): void
    {
        $config = new ModuleConfig(enableSessionHistory: false);

        $decorator = $this->createMock(UserSessionHistoryDecorator::class);
        $decorator->expects(self::never())->method('registerLogin');

        $listener = new SessionHistoryListener($decorator, $config);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);
    }
    public function testOnAfterLoginRecordsSessionWhenEnabled(): void
    {
        $config = new ModuleConfig(enableSessionHistory: true);

        $decorator = $this->createMock(UserSessionHistoryDecorator::class);
        $decorator->expects(self::once())->method('registerLogin')->with(
            self::isInstanceOf(User::class),
        );

        $listener = new SessionHistoryListener($decorator, $config);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);
    }
}
