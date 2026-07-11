<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Listener;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Listener\PasswordExpirationListener;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\ExpireService;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class PasswordExpirationListenerTest extends TestCase
{

    public function testOnAfterLoginDoesNotFlashWhenPasswordNotExpired(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true);

        $expireService = $this->createMock(ExpireService::class);
        $expireService->expects(self::once())->method('checkPasswordExpiration')->willReturn(false);

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::never())->method('set');

        $translator = $this->createMock(TranslatorInterface::class);

        $listener = new PasswordExpirationListener($expireService, $config, $translator, $flash);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);
    }

    public function testOnAfterLoginDoesNothingWhenDisabled(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: false);

        $expireService = $this->createMock(ExpireService::class);
        $expireService->expects(self::never())->method('checkPasswordExpiration');

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::never())->method('set');

        $translator = $this->createMock(TranslatorInterface::class);

        $listener = new PasswordExpirationListener($expireService, $config, $translator, $flash);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);
    }

    public function testOnAfterLoginFlashesWarningWhenPasswordExpired(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true);

        $expireService = $this->createMock(ExpireService::class);
        $expireService->expects(self::once())->method('checkPasswordExpiration')->with(
            self::isInstanceOf(User::class),
        )->willReturn(true);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(fn (string $id) => $id);

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('set')->with(FlashType::WARNING, 'voyti.security.password_expired');

        $listener = new PasswordExpirationListener($expireService, $config, $translator, $flash);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);
    }
    public function testOnAfterLoginWorksWithoutFlashService(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true);

        $expireService = $this->createMock(ExpireService::class);
        $expireService->expects(self::once())->method('checkPasswordExpiration')->willReturn(true);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(fn (string $id) => $id);

        $listener = new PasswordExpirationListener($expireService, $config, $translator);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);

        $this->addToAssertionCount(1);
    }
}
