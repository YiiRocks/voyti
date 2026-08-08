<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Account;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Controller\Account\SessionController;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\UserSessionFactoryTrait;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class SessionControllerTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use TestContainerTrait;
    use UserFactoryTrait;
    use UserSessionFactoryTrait;

    private CurrentUser $currentUser;
    private EventCaptureDispatcher $eventDispatcher;
    private FlashInterface&MockObject $flash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->currentUser = $this->createCurrentUser();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->eventDispatcher = new EventCaptureDispatcher();
    }

    public function testIndexFlagsCurrentDevice(): void
    {
        $user = $this->createUser(username: 'sessionuser', email: 'sessionuser@example.com');
        $this->authenticateAs($user);

        $this->createUserSession($user->getIdOrZero(), 'current-session', '203.0.113.1');
        $this->createUserSession($user->getIdOrZero(), 'other-session', '203.0.113.2');

        $controller = $this->createController();
        $session = $this->getTestContainer()->get(SessionInterface::class);
        $session->open();
        $session->setId('current-session');

        $html = (string) $controller->index()->getBody();

        // Both sessions render, and exactly one is flagged as the current device.
        self::assertStringContainsString('203.0.113.1', $html);
        self::assertStringContainsString('203.0.113.2', $html);
        self::assertSame(1, substr_count($html, 'This device'));
    }

    public function testTerminateCurrentSessionLogsOutAndRedirectsToLogin(): void
    {
        $user = $this->createUser(username: 'sessionuser', email: 'sessionuser@example.com');
        $this->authenticateAs($user);
        $this->createUserSession($user->getIdOrZero(), 'current-session', '203.0.113.1');

        $controller = $this->createController();
        $session = $this->getTestContainer()->get(SessionInterface::class);
        $session->open();
        $session->setId('current-session');

        $result = $controller->terminate('current-session');

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('session-login', $result->getHeaderLine('Location'));
        $this->assertTrue($this->currentUser->isGuest());
        $event = $this->eventDispatcher->getEvent(SessionEvent::class);
        $this->assertInstanceOf(SessionEvent::class, $event);
        // The event carries the termination type in its data.
        $this->assertSame(SessionEvent::SESSION_TERMINATED, $event->getData()['type'] ?? null);
    }

    public function testTerminateOtherSessionRevokesItAndRedirects(): void
    {
        $user = $this->createUser(username: 'sessionuser', email: 'sessionuser@example.com');
        $this->authenticateAs($user);
        $this->createUserSession($user->getIdOrZero(), 'other-session', '203.0.113.1');

        $result = $this->createController()->terminate('other-session');

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('user-account-sessions', $result->getHeaderLine('Location'));
        $revoked = UserSessions::findByUserIdAndSessionId($user->getIdOrZero(), 'other-session');
        $this->assertNotNull($revoked);
        $this->assertTrue($revoked->isRevoked());
    }

    public function testTerminateUnknownSessionShowsError(): void
    {
        $user = $this->createUser(username: 'sessionuser', email: 'sessionuser@example.com');
        $this->authenticateAs($user);

        $html = (string) $this->createController()->terminate('unknown-session')->getBody();

        self::assertStringContainsString('Session not found', $html);
    }

    private function authenticateAs(User $user): void
    {
        $this->currentUser->login($user);
    }

    private function createController(): SessionController
    {
        return $this->getTestContainer([
            CurrentUser::class => $this->currentUser,
            EventDispatcherInterface::class => $this->eventDispatcher,
            FlashInterface::class => $this->flash,
        ])->get(SessionController::class);
    }
}
