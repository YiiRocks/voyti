<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Listener;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\Listener\AdminNotificationListener;
use YiiRocks\Voyti\Listener\PasswordExpirationListener;
use YiiRocks\Voyti\Listener\SessionListener;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Service\Password\ExpireService;
use YiiRocks\Voyti\Service\UserSession\UserSessionDecorator;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeSession;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\MailServiceFactoryTrait;
use YiiRocks\Voyti\tests\Support\UserSessionFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use Yiisoft\Session\Flash\FlashInterface;

#[AllowMockObjectsWithoutExpectations]
final class ListenerTest extends DatabaseTestCase
{
    use MailServiceFactoryTrait;
    use UserSessionFactoryTrait;

    public function testAdminNotificationDoesNotSendEmailWhenNull(): void
    {
        $config = VoytiConfigFactory::create(mailAdminOnRegister: null);

        $mailCapture = new MailCapture();
        $listener = new AdminNotificationListener($this->createMailService($mailCapture), $config);
        $listener->onAfterRegister(new AfterRegisterEvent(new User()));

        self::assertNull($mailCapture->getLastMessage());
    }

    public function testAdminNotificationSendsEmailWhenConfigured(): void
    {
        $config = VoytiConfigFactory::create(mailAdminOnRegister: 'admin@example.com');

        $mailCapture = new MailCapture();
        $listener = new AdminNotificationListener($this->createMailService($mailCapture), $config);

        $user = new User();
        $user->setUsername('newbie');
        $listener->onAfterRegister(new AfterRegisterEvent($user));

        $message = $mailCapture->getLastMessage();
        self::assertNotNull($message);
        self::assertSame('admin@example.com', $message->getTo());
    }

    public function testPasswordExpirationDoesNotFlashWhenPasswordNotExpired(): void
    {
        $expireService = new ExpireService(VoytiConfigFactory::create(maxPasswordAge: 90));

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::never())->method('set');

        $listener = new PasswordExpirationListener($expireService, $this->createTranslator(), $flash);
        $user = new User();
        $user->setPasswordChangedAt(time());

        $listener->onAfterLogin(new AfterLoginEvent($user));
    }

    public function testPasswordExpirationFlashesWarningWhenPasswordExpired(): void
    {
        $expireService = new ExpireService(VoytiConfigFactory::create(maxPasswordAge: 90));

        $flash = $this->createMock(FlashInterface::class);
        $flash->expects(self::once())->method('set')->with(
            FlashType::WARNING,
            'Your password has expired. Please set a new one.',
        );

        $listener = new PasswordExpirationListener($expireService, $this->createTranslator(), $flash);
        $listener->onAfterLogin(new AfterLoginEvent(new User()));
    }

    public function testPasswordExpirationWorksWithoutFlashService(): void
    {
        $expireService = new ExpireService(VoytiConfigFactory::create(maxPasswordAge: 90));

        $listener = new PasswordExpirationListener($expireService, $this->createTranslator());
        $listener->onAfterLogin(new AfterLoginEvent(new User()));

        $this->addToAssertionCount(1);
    }

    public function testSessionListenerPassesPreviousSessionIdThrough(): void
    {
        $session = new FakeSession();
        $session->setId('current-session');
        $session->open();
        $decorator = new UserSessionDecorator(new EventCaptureDispatcher(), VoytiConfigFactory::create(), $session);

        $this->createUserSession(0, 'old-session-id');

        $listener = new SessionListener($decorator);
        $listener->onAfterLogin(new AfterLoginEvent(new User(), previousSessionId: 'old-session-id'));

        // The previous session id is passed through and its record replaced (deleted), and the
        // current session is recorded.
        self::assertNull(UserSessions::findByUserIdAndSessionId(0, 'old-session-id'));
        self::assertNotNull(UserSessions::findByUserIdAndSessionId(0, 'current-session'));
    }

    public function testSessionListenerRecordsSession(): void
    {
        $session = new FakeSession();
        $session->setId('current-session');
        $session->open();
        $decorator = new UserSessionDecorator(new EventCaptureDispatcher(), VoytiConfigFactory::create(), $session);

        $listener = new SessionListener($decorator);
        $listener->onAfterLogin(new AfterLoginEvent(new User()));

        self::assertNotNull(UserSessions::findByUserIdAndSessionId(0, 'current-session'));
    }
}
