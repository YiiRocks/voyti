<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Listener;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Listener\PasswordExpirationListener;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Session\Flash\FlashInterface;

#[AllowMockObjectsWithoutExpectations]
final class PasswordExpirationListenerTest extends TestCase
{
    public function testOnAfterLoginDoesNotFlashWhenPasswordNotExpired(): void
    {
        $config = ModuleConfigFactory::create(enablePasswordExpiration: true);

        $expireService = $this->createMock(ExpireService::class);
        $expireService->expects(self::once())->method('checkPasswordExpiration')->willReturn(false);

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::never())->method('set');

        $translator = $this->createTranslator();

        $listener = new PasswordExpirationListener($expireService, $config, $translator, $flash);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);
    }

    public function testOnAfterLoginDoesNothingWhenDisabled(): void
    {
        $config = ModuleConfigFactory::create(enablePasswordExpiration: false);

        $expireService = $this->createMock(ExpireService::class);
        $expireService->expects(self::never())->method('checkPasswordExpiration');

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::never())->method('set');

        $translator = $this->createTranslator();

        $listener = new PasswordExpirationListener($expireService, $config, $translator, $flash);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);
    }

    public function testOnAfterLoginFlashesWarningWhenPasswordExpired(): void
    {
        $config = ModuleConfigFactory::create(enablePasswordExpiration: true);

        $expireService = $this->createMock(ExpireService::class);
        $expireService->expects(self::once())->method('checkPasswordExpiration')->with(
            self::isInstanceOf(User::class),
        )->willReturn(true);

        $translator = $this->createTranslator();

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('set')->with(
            FlashType::WARNING,
            'Your password has expired. Please set a new one.',
        );

        $listener = new PasswordExpirationListener($expireService, $config, $translator, $flash);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);
    }
    public function testOnAfterLoginWorksWithoutFlashService(): void
    {
        $config = ModuleConfigFactory::create(enablePasswordExpiration: true);

        $expireService = $this->createMock(ExpireService::class);
        $expireService->expects(self::once())->method('checkPasswordExpiration')->willReturn(true);

        $translator = $this->createTranslator();

        $listener = new PasswordExpirationListener($expireService, $config, $translator);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);

        $this->addToAssertionCount(1);
    }
}
