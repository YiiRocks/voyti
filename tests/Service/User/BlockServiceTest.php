<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSessionHistory;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Service\User\BlockService;
use YiiRocks\Voyti\Service\UserSessionHistory\TerminateUserSessionsService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class BlockServiceTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConnectionProvider::set($this->getDb());
        $db = $this->getDb();
        $db->createCommand('CREATE TABLE {{%user}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            auth_key VARCHAR(255) NOT NULL,
            unconfirmed_email VARCHAR(255),
            registration_ip VARCHAR(45),
            flags INTEGER NOT NULL DEFAULT 0,
            confirmed_at INTEGER,
            blocked_at INTEGER,
            updated_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL,
            last_login_at INTEGER,
            auth_tf_key VARCHAR(64),
            auth_tf_enabled INTEGER DEFAULT 0,
            password_changed_at INTEGER,
            last_login_ip VARCHAR(45),
            gdpr_deleted INTEGER DEFAULT 0,
            gdpr_consent INTEGER DEFAULT 0,
            gdpr_consent_date INTEGER,
            auth_tf_type VARCHAR(20)
        )')->execute();
        $db->createCommand('CREATE TABLE {{%user_session_history}} (
            user_id INTEGER NOT NULL,
            session_id VARCHAR(255) NOT NULL,
            user_agent TEXT,
            ip VARCHAR(45),
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            PRIMARY KEY (user_id, session_id)
        )')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_session_history}}')->execute();
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testGetUserIdUsesZeroFallbackWhenUserHasNoId(): void
    {
        // An unsaved user has a null id; the private getUserId() fallback must be
        // exactly 0 (not -1 or 1). save() always populates the primary key on
        // insert, so the null branch cannot be observed by going through run();
        // we invoke the private method directly via reflection.
        $user = new User();
        $user->setUsername('unsaved');
        $user->setEmail('unsaved@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');

        self::assertNull($user->getId());

        $service = new BlockService(new BlockEventCollector(), new TerminateUserSessionsService(new BlockEventCollector()));

        $method = new \ReflectionMethod(BlockService::class, 'getUserId');
        $result = $method->invoke($service, $user);

        self::assertSame(0, $result);
    }

    public function testRunBlocksUnblockedUserDispatchesEventsAndTerminatesSessions(): void
    {
        $user = $this->createUser('alice');
        $userId = (int) $user->getId();
        $this->insertSession($userId, 'alice-sess');
        self::assertFalse($user->isBlocked());

        $dispatcher = new BlockEventCollector();
        $service = new BlockService($dispatcher, new TerminateUserSessionsService($dispatcher));

        $before = time();
        $result = $service->run($user);
        $after = time();

        self::assertTrue($result);
        self::assertTrue($user->isBlocked());
        self::assertGreaterThanOrEqual($before, $user->getBlockedAt());
        self::assertLessThanOrEqual($after, $user->getBlockedAt());

        $reloaded = User::query()->findByPk($user->getId());
        self::assertTrue($reloaded->isBlocked());
        self::assertCount(0, UserSessionHistory::query()->where(['user_id' => $userId])->all());

        $events = $dispatcher->events();
        self::assertCount(3, $events);
        self::assertInstanceOf(UserEvent::class, $events[0]);
        self::assertInstanceOf(UserEvent::class, $events[1]);
        self::assertSame($user, $events[0]->getUser());
        self::assertSame($user, $events[1]->getUser());
        self::assertInstanceOf(SessionEvent::class, $events[2]);
        self::assertSame($userId, $events[2]->getUserId());
        self::assertSame(['type' => SessionEvent::SESSION_TERMINATED], $events[2]->getData());
    }

    public function testRunUnblocksBlockedUserDispatchesTwoEventsAndDoesNotTerminateSessions(): void
    {
        $user = $this->createUser('bob');
        $user->setBlockedAt(time());
        $user->save();
        $userId = (int) $user->getId();
        $this->insertSession($userId, 'bob-sess');
        self::assertTrue($user->isBlocked());

        $dispatcher = new BlockEventCollector();
        $service = new BlockService($dispatcher, new TerminateUserSessionsService($dispatcher));

        $result = $service->run($user);

        self::assertTrue($result);
        self::assertFalse($user->isBlocked());
        self::assertNull($user->getBlockedAt());

        $reloaded = User::query()->findByPk($user->getId());
        self::assertFalse($reloaded->isBlocked());
        self::assertCount(1, UserSessionHistory::query()->where(['user_id' => $userId])->all());

        $events = $dispatcher->events();
        self::assertCount(2, $events);
        self::assertInstanceOf(UserEvent::class, $events[0]);
        self::assertInstanceOf(UserEvent::class, $events[1]);
    }

    private function createUser(string $username): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username . '@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->save();

        return $user;
    }

    private function insertSession(int $userId, string $sessionId): void
    {
        $session = new UserSessionHistory();
        $session->setUserId($userId);
        $session->setSessionId($sessionId);
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();
    }
}

final class BlockEventCollector implements EventDispatcherInterface
{
    /** @var list<object> */
    private array $events = [];

    #[\Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;
        return $event;
    }

    /**
     * @return list<object>
     */
    public function events(): array
    {
        return $this->events;
    }
}
