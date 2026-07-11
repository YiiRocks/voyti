<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Listener;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\Listener\AdminNotificationListener;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\MailService;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class AdminNotificationListenerTest extends TestCase
{

    public function testOnAfterRegisterDoesNotSendEmailWhenNull(): void
    {
        $config = new ModuleConfig(mailAdminOnRegister: null);

        $mailService = $this->createMock(MailService::class);
        $mailService->expects(self::never())->method('sendAdminNotification');

        $listener = new AdminNotificationListener($mailService, $config);
        $user = new User();
        $event = new AfterRegisterEvent($user);

        $listener->onAfterRegister($event);
    }
    public function testOnAfterRegisterSendsEmailWhenConfigured(): void
    {
        $config = new ModuleConfig(mailAdminOnRegister: 'admin@example.com');

        $mailService = $this->createMock(MailService::class);
        $mailService->expects(self::once())->method('sendAdminNotification')->with(
            'admin@example.com',
            self::isInstanceOf(User::class),
        );

        $listener = new AdminNotificationListener($mailService, $config);
        $user = new User();
        $event = new AfterRegisterEvent($user);

        $listener->onAfterRegister($event);
    }

    public function testOnAfterRegisterWithEmptyStringDoesNotSend(): void
    {
        $config = new ModuleConfig(mailAdminOnRegister: '');

        $mailService = $this->createMock(MailService::class);
        $mailService->expects(self::once())->method('sendAdminNotification');

        $listener = new AdminNotificationListener($mailService, $config);
        $user = new User();
        $event = new AfterRegisterEvent($user);

        $listener->onAfterRegister($event);
    }
}
