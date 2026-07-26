<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Listener;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Listener\PasswordExpirationListener;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Session\Flash\FlashInterface;

#[AllowMockObjectsWithoutExpectations]
final class PasswordExpirationListenerTest extends TestCase
{
    public function testOnAfterLoginDoesNotFlashWhenPasswordNotExpired(): void
    {
        $expireService = $this->createMock(ExpireService::class);
        $expireService->expects(self::once())->method('isExpired')->willReturn(false);

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::never())->method('set');

        $translator = $this->createTranslator();

        $listener = new PasswordExpirationListener($expireService, $translator, $flash);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);
    }

    public function testOnAfterLoginFlashesWarningWhenPasswordExpired(): void
    {
        $expireService = $this->createMock(ExpireService::class);
        $expireService->expects(self::once())->method('isExpired')->with(
            self::isInstanceOf(User::class),
        )->willReturn(true);

        $translator = $this->createTranslator();

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('set')->with(
            FlashType::WARNING,
            'Your password has expired. Please set a new one.',
        );

        $listener = new PasswordExpirationListener($expireService, $translator, $flash);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);
    }

    public function testOnAfterLoginWorksWithoutFlashService(): void
    {
        $expireService = $this->createMock(ExpireService::class);
        $expireService->expects(self::once())->method('isExpired')->willReturn(true);

        $translator = $this->createTranslator();

        $listener = new PasswordExpirationListener($expireService, $translator);
        $user = new User();
        $event = new AfterLoginEvent($user);

        $listener->onAfterLogin($event);

        $this->addToAssertionCount(1);
    }
}
