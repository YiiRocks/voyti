<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\UserSession;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\UserSession\UserSessionDecorator;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeSession;

#[AllowMockObjectsWithoutExpectations]
final class UserSessionDecoratorTest extends TestCase
{
    use DatabaseSetupTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testAgePruneDoesNotDeleteExpiredSessionsOfOtherUsers(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig();

        $session = $this->createOpenSession('sessageb');
        $decorator = new UserSessionDecorator($eventDispatcher, $config, $session);

        $userA = $this->createUser('usera', 'usera@example.com');
        $userIdA = (int) $userA->getId();

        $this->createUserSession($userIdA, 'a_expired', $config->rememberLoginLifespan + 1);

        $userB = $this->createUser('userb', 'userb@example.com');

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent';

        $decorator->registerLogin($userB);

        $remainingA = UserSessions::query()->where(['user_id' => $userIdA])->all();
        self::assertCount(1, $remainingA);
        self::assertSame('a_expired', $remainingA[0]->getSessionId());

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function testRegisterLoginFallsBackToLocalhostWhenNoRemoteAddr(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig(disableIpLogging: false);

        $session = $this->createOpenSession('sessfallback');
        $decorator = new UserSessionDecorator($eventDispatcher, $config, $session);

        $user = $this->createUser('fallback', 'fallback@example.com');

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        $decorator->registerLogin($user);

        $sessions = UserSessions::query()->where(['user_id' => (int) $user->getId()])->all();
        self::assertCount(1, $sessions);
        self::assertSame('127.0.0.1', $sessions[0]->getIp());
        self::assertNull($sessions[0]->getUserAgent());
    }

    public function testRegisterLoginPrunesSessionsOlderThanRememberLoginLifespan(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig();

        $session = $this->createOpenSession('sessage');
        $decorator = new UserSessionDecorator($eventDispatcher, $config, $session);

        $user = $this->createUser('agetest', 'agetest@example.com');
        $userId = (int) $user->getId();

        $lifespan = $config->rememberLoginLifespan;

        $this->createUserSession($userId, 'old_expired', $lifespan + 1);
        $this->createUserSession($userId, 'old_fresh', $lifespan - 1);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent';

        $decorator->registerLogin($user);

        $sessions = UserSessions::query()->where(['user_id' => $userId])->all();
        $sessionIds = array_map(static fn(UserSessions $s): string => $s->getSessionId(), $sessions);

        self::assertContains('sessage', $sessionIds);
        self::assertContains('old_fresh', $sessionIds);
        self::assertNotContains('old_expired', $sessionIds);

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function testRegisterLoginRecordsSession(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig();

        $session = $this->createOpenSession('sess123');
        $decorator = new UserSessionDecorator($eventDispatcher, $config, $session);

        $user = $this->createUser('test', 'test@example.com');

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent';

        $decorator->registerLogin($user);

        $sessions = UserSessions::query()->where(['user_id' => (int) $user->getId()])->all();
        self::assertCount(1, $sessions);
        self::assertSame('sess123', $sessions[0]->getSessionId());
        self::assertSame('192.168.1.1', $sessions[0]->getIp());
        self::assertSame('TestAgent', $sessions[0]->getUserAgent());
        self::assertSame((int) $user->getId(), $sessions[0]->getUserId());
        self::assertNotSame(0, $sessions[0]->getCreatedAt());
        self::assertNotSame(0, $sessions[0]->getUpdatedAt());

        $event = $eventDispatcher->getEvent(SessionEvent::class);
        self::assertInstanceOf(SessionEvent::class, $event);
        self::assertSame(['type' => SessionEvent::SESSION_CREATED], $event->getData());

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function testRegisterLoginReplacesStalePreviousSessionForSameUser(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig();

        $user = $this->createUser('replacetest', 'replacetest@example.com');
        $userId = (int) $user->getId();

        $this->createUserSession($userId, 'stale-session-id', 100);

        $session = $this->createOpenSession('fresh-session-id');
        $decorator = new UserSessionDecorator($eventDispatcher, $config, $session);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent';

        $decorator->registerLogin($user, 'stale-session-id');

        $sessions = UserSessions::query()->where(['user_id' => $userId])->all();
        $sessionIds = array_map(static fn(UserSessions $s): string => $s->getSessionId(), $sessions);

        self::assertSame(['fresh-session-id'], $sessionIds);

        $events = array_values(array_filter(
            $eventDispatcher->getEvents(),
            static fn(object $event): bool => $event instanceof SessionEvent,
        ));
        self::assertCount(2, $events);
        self::assertSame(['type' => SessionEvent::SESSION_TERMINATED], $events[0]->getData());
        self::assertSame('stale-session-id', $events[0]->getSessionId());
        self::assertSame(['type' => SessionEvent::SESSION_CREATED], $events[1]->getData());
        self::assertSame('fresh-session-id', $events[1]->getSessionId());

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function testRegisterLoginWithDifferentPreviousSessionIdButNoMatchingRowIsNoop(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig();

        $session = $this->createOpenSession('sessreplacemiss');
        $decorator = new UserSessionDecorator($eventDispatcher, $config, $session);

        $user = $this->createUser('replacemiss', 'replacemiss@example.com');

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $decorator->registerLogin($user, 'nonexistent-session-id');

        $sessions = UserSessions::query()->where(['user_id' => (int) $user->getId()])->all();
        self::assertCount(1, $sessions);
        self::assertSame('sessreplacemiss', $sessions[0]->getSessionId());

        $events = array_filter(
            $eventDispatcher->getEvents(),
            static fn(object $event): bool => $event instanceof SessionEvent && $event->getData() === ['type' => SessionEvent::SESSION_TERMINATED],
        );
        self::assertCount(0, $events);

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testRegisterLoginWithDisableIpLogging(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig(disableIpLogging: true);

        $session = $this->createOpenSession('sess456');
        $decorator = new UserSessionDecorator($eventDispatcher, $config, $session);

        $user = $this->createUser('test2', 'test2@example.com');

        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent';

        $decorator->registerLogin($user);

        $sessions = UserSessions::query()->where(['user_id' => (int) $user->getId()])->all();
        self::assertCount(1, $sessions);
        self::assertSame('127.0.0.1', $sessions[0]->getIp());

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function testRegisterLoginWithEmptyPreviousSessionIdSkipsReplace(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig();

        $session = $this->createOpenSession('sessemptyprev');
        $decorator = new UserSessionDecorator($eventDispatcher, $config, $session);

        $user = $this->createUser('emptyprev', 'emptyprev@example.com');

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $decorator->registerLogin($user, '');

        $sessions = UserSessions::query()->where(['user_id' => (int) $user->getId()])->all();
        self::assertCount(1, $sessions);

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testRegisterLoginWithNullUserIdRecordsZero(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig();

        $decorator = new UserSessionDecorator($eventDispatcher, $config);

        $user = new User();
        $user->setUsername('noid');
        $user->setEmail('noid@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');

        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $decorator->registerLogin($user);

        $sessions = UserSessions::query()->where(['user_id' => 0])->all();
        self::assertCount(1, $sessions);
        self::assertSame(0, $sessions[0]->getUserId());

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testRegisterLoginWithPreviousSessionIdEqualToCurrentSkipsReplace(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig();

        $session = $this->createOpenSession('sesssame');
        $decorator = new UserSessionDecorator($eventDispatcher, $config, $session);

        $user = $this->createUser('samesession', 'samesession@example.com');

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $decorator->registerLogin($user, 'sesssame');

        $sessions = UserSessions::query()->where(['user_id' => (int) $user->getId()])->all();
        self::assertCount(1, $sessions);

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testRegisterLoginWithSessionNullId(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig();

        $decorator = new UserSessionDecorator($eventDispatcher, $config);

        $user = $this->createUser('nosess', 'nosess@example.com');

        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $decorator->registerLogin($user);

        $sessions = UserSessions::query()->where(['user_id' => (int) $user->getId()])->all();
        self::assertCount(1, $sessions);
        self::assertSame('', $sessions[0]->getSessionId());

        unset($_SERVER['REMOTE_ADDR']);
    }

    private function createOpenSession(string $id): FakeSession
    {
        $session = new FakeSession();
        $session->setId($id);
        $session->open();

        return $session;
    }

    private function createUser(string $username, string $email): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }

    private function createUserSession(int $userId, string $sessionId, int $ageOffset): UserSessions
    {
        $sh = new UserSessions();
        $sh->setUserId($userId);
        $sh->setSessionId($sessionId);
        $sh->setIp('127.0.0.1');
        $sh->setCreatedAt(time() - $ageOffset);
        $sh->setUpdatedAt(time() - $ageOffset);
        $sh->save();

        return $sh;
    }
}
