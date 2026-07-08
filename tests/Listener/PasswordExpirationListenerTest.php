<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Listener;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Listener\PasswordExpirationListener;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\ExpireService;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class PasswordExpirationListenerTest extends TestCase
{
    public function testOnAfterLoginChecksExpirationWhenEnabled(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true);

        $expireService = $this->createMock(ExpireService::class);
        $expireService->expects(self::once())->method('checkPasswordExpiration')->with(
            self::isInstanceOf(User::class),
        );

        $listener = new PasswordExpirationListener($expireService, $config);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);
    }

    public function testOnAfterLoginDoesNothingWhenDisabled(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: false);

        $expireService = $this->createMock(ExpireService::class);
        $expireService->expects(self::never())->method('checkPasswordExpiration');

        $listener = new PasswordExpirationListener($expireService, $config);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);
    }
}
