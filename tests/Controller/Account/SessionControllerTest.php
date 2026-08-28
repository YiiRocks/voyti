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
        // A revoked session must be filtered out of the list entirely.
        $revoked = $this->createUserSession($user->getIdOrZero(), 'revoked-session', '203.0.113.9');
        $revoked->setRevokedAt(time());
        $revoked->save();

        $controller = $this->createController();
        $session = $this->getTestContainer()->get(SessionInterface::class);
        $session->open();
        $session->setId('current-session');

        $html = (string) $controller->index()->getBody();

        // Both active sessions render, exactly one is flagged as the current device, and the
        // revoked session does not appear at all.
        self::assertStringContainsString('203.0.113.1', $html);
        self::assertStringContainsString('203.0.113.2', $html);
        self::assertStringNotContainsString('203.0.113.9', $html);
        self::assertSame(1, substr_count($html, 'This device'));
        // Only the non-current session gets a terminate form, and its URL carries the session id.
        self::assertStringContainsString('sessionId=other-session', $html);
        self::assertStringNotContainsString('sessionId=current-session', $html);
    }

    public function testTerminate(): void
    {
        // Current session: logs out, redirects to login, and dispatches SessionEvent
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
        $this->assertSame(SessionEvent::SESSION_TERMINATED, $event->getData()['type'] ?? null);

        // Other user's session: revokes it and redirects back
        $other = $this->createUser(username: 'sessionuser2', email: 'sessionuser2@example.com');
        $this->authenticateAs($other);
        $this->createUserSession($other->getIdOrZero(), 'other-session', '203.0.113.1');

        $result = $this->createController()->terminate('other-session');

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('user-account-sessions', $result->getHeaderLine('Location'));
        $revoked = UserSessions::findByUserIdAndSessionId($other->getIdOrZero(), 'other-session');
        $this->assertNotNull($revoked);
        $this->assertTrue($revoked->isRevoked());

        // Unknown session: shows error
        $another = $this->createUser(username: 'sessionuser3', email: 'sessionuser3@example.com');
        $this->authenticateAs($another);

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
