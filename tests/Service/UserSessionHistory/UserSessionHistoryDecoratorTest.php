<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\UserSessionHistory;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSessionHistory;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\UserSessionHistory\UserSessionHistoryDecorator;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Session\SessionInterface;

final class UserSessionHistoryDecoratorTest extends TestCase
{
    private bool $hadRemoteAddress = false;
    private bool $hadUserAgent = false;
    private string $remoteAddress = '';
    private string $userAgent = '';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->hadRemoteAddress = array_key_exists('REMOTE_ADDR', $_SERVER);
        $this->remoteAddress = $this->hadRemoteAddress ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $this->hadUserAgent = array_key_exists('HTTP_USER_AGENT', $_SERVER);
        $this->userAgent = $this->hadUserAgent ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        ConnectionProvider::set($this->getDb());
        $db = $this->getDb();
        $db->createCommand('CREATE TABLE {{%user}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            auth_key VARCHAR(32) NOT NULL,
            updated_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL
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
        if ($this->hadRemoteAddress) {
            $_SERVER['REMOTE_ADDR'] = $this->remoteAddress;
        } else {
            unset($_SERVER['REMOTE_ADDR']);
        }
        if ($this->hadUserAgent) {
            $_SERVER['HTTP_USER_AGENT'] = $this->userAgent;
        } else {
            unset($_SERVER['HTTP_USER_AGENT']);
        }

        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_session_history}}')->execute();
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testRegisterLoginDefaultsUserIdToZeroWhenUserHasNoId(): void
    {
        $config = ModuleConfig::fromArray([
            'enableSessionHistory' => true,
            'numberSessionHistory' => false,
        ]);
        $decorator = new UserSessionHistoryDecorator(new EventCollector(), $config, new FakeSession('anon-sess'));

        // A brand new, unsaved user has no id yet.
        $decorator->registerLogin(new User());

        $rows = UserSessionHistory::query()->where(['user_id' => 0, 'session_id' => 'anon-sess'])->all();
        self::assertCount(1, $rows);
        self::assertSame(0, $rows[0]->getUserId());
    }

    public function testRegisterLoginDispatchesSessionCreatedEvent(): void
    {
        $config = ModuleConfig::fromArray([
            'enableSessionHistory' => true,
            'numberSessionHistory' => false,
        ]);
        $user = $this->createUser('gina', 'gina@example.com');
        $userId = (int) $user->getId();

        $dispatcher = new EventCollector();
        $decorator = new UserSessionHistoryDecorator($dispatcher, $config, new FakeSession('gina-session'));
        $decorator->registerLogin($user);

        $events = $dispatcher->events();
        self::assertCount(1, $events);
        self::assertInstanceOf(SessionEvent::class, $events[0]);
        self::assertSame($userId, $events[0]->getUserId());
        self::assertSame('gina-session', $events[0]->getSessionId());
        self::assertSame(['type' => SessionEvent::SESSION_CREATED], $events[0]->getData());
    }

    public function testRegisterLoginDoesNotDispatchEventWhenSessionHistoryDisabled(): void
    {
        $config = ModuleConfig::fromArray(['enableSessionHistory' => false]);
        $user = $this->createUser('holly', 'holly@example.com');

        $dispatcher = new EventCollector();
        $decorator = new UserSessionHistoryDecorator($dispatcher, $config, new FakeSession('holly-session'));
        $decorator->registerLogin($user);

        self::assertCount(0, $dispatcher->events());
    }

    public function testRegisterLoginDoesNotPruneWhenSessionHistoryLimitDisabled(): void
    {
        $config = ModuleConfig::fromArray([
            'enableSessionHistory' => true,
            'numberSessionHistory' => false,
        ]);
        $user = $this->createUser('vera', 'vera@example.com');
        $userId = (int) $user->getId();

        $this->insertSession($userId, 'old-1', 100);
        $this->insertSession($userId, 'old-2', 200);
        $this->insertSession($userId, 'old-3', 300);

        $decorator = new UserSessionHistoryDecorator(new EventCollector(), $config, new FakeSession('new-session'));
        $decorator->registerLogin($user);

        $rows = UserSessionHistory::query()->where(['user_id' => $userId])->all();
        self::assertCount(4, $rows);
    }

    public function testRegisterLoginPersistsSessionHistoryRecordWithUserSessionIpAndUserAgent(): void
    {
        $config = ModuleConfig::fromArray([
            'enableSessionHistory' => true,
            'disableIpLogging' => false,
            'numberSessionHistory' => false,
        ]);
        $user = $this->createUser('carol', 'carol@example.com');
        $userId = (int) $user->getId();

        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';

        $decorator = new UserSessionHistoryDecorator(new EventCollector(), $config, new FakeSession('sess-full-test'));

        $before = time();
        $decorator->registerLogin($user);
        $after = time();

        $rows = UserSessionHistory::query()->where(['user_id' => $userId])->all();
        self::assertCount(1, $rows);
        $row = $rows[0];
        self::assertSame($userId, $row->getUserId());
        self::assertSame('sess-full-test', $row->getSessionId());
        self::assertSame('203.0.113.7', $row->getIp());
        self::assertSame('TestAgent/1.0', $row->getUserAgent());
        self::assertGreaterThanOrEqual($before, $row->getCreatedAt());
        self::assertLessThanOrEqual($after, $row->getCreatedAt());
        self::assertGreaterThanOrEqual($before, $row->getUpdatedAt());
        self::assertLessThanOrEqual($after, $row->getUpdatedAt());
    }

    public function testRegisterLoginPruneOnlyAffectsCurrentUsersSessions(): void
    {
        // pruneOldSessions() sorts ascending (the 'DESC' direction key is a string, not the
        // SORT_DESC constant, so the query builder never appends "DESC"), so the oldest
        // sessions are kept and the excess is trimmed off the newest end of the list.
        $config = ModuleConfig::fromArray([
            'enableSessionHistory' => true,
            'numberSessionHistory' => 1,
        ]);

        $userA = $this->createUser('usera', 'usera@example.com');
        $userIdA = (int) $userA->getId();
        $userB = $this->createUser('userb', 'userb@example.com');
        $userIdB = (int) $userB->getId();

        $this->insertSession($userIdA, 'a-old', 50);
        $this->insertSession($userIdB, 'b-old1', 100);
        $this->insertSession($userIdB, 'b-old2', 200);

        $decorator = new UserSessionHistoryDecorator(new EventCollector(), $config, new FakeSession('a-new'));
        $decorator->registerLogin($userA);

        $rowsA = UserSessionHistory::query()->where(['user_id' => $userIdA])->all();
        self::assertCount(1, $rowsA);
        self::assertSame('a-old', $rowsA[0]->getSessionId());

        $rowsB = UserSessionHistory::query()->where(['user_id' => $userIdB])->all();
        self::assertCount(2, $rowsB);
        $sessionIdsB = array_map(static fn (UserSessionHistory $row): string => $row->getSessionId(), $rowsB);
        sort($sessionIdsB);
        self::assertSame(['b-old1', 'b-old2'], $sessionIdsB);
    }

    public function testRegisterLoginPrunesSessionsBeyondConfiguredLimit(): void
    {
        $config = ModuleConfig::fromArray([
            'enableSessionHistory' => true,
            'numberSessionHistory' => 2,
        ]);
        $user = $this->createUser('dave', 'dave@example.com');
        $userId = (int) $user->getId();

        $this->insertSession($userId, 'old-1', 1000);
        $this->insertSession($userId, 'old-2', 2000);

        $decorator = new UserSessionHistoryDecorator(new EventCollector(), $config, new FakeSession('new-session'));
        $decorator->registerLogin($user);

        $rows = UserSessionHistory::query()->where(['user_id' => $userId])->all();
        self::assertCount(2, $rows);
        $sessionIds = array_map(static fn (UserSessionHistory $row): string => $row->getSessionId(), $rows);
        self::assertContains('old-1', $sessionIds);
        self::assertContains('old-2', $sessionIds);
        self::assertNotContains('new-session', $sessionIds);
    }

    public function testRegisterLoginPrunesSessionsForUserWithoutIdUsingZeroUserId(): void
    {
        $config = ModuleConfig::fromArray([
            'enableSessionHistory' => true,
            'numberSessionHistory' => 1,
        ]);

        $this->insertSession(0, 'anon-old', 1000);

        $decorator = new UserSessionHistoryDecorator(new EventCollector(), $config, new FakeSession('anon-new'));
        $decorator->registerLogin(new User());

        $rows = UserSessionHistory::query()->where(['user_id' => 0])->all();
        self::assertCount(1, $rows);
        self::assertSame('anon-old', $rows[0]->getSessionId());
    }

    public function testRegisterLoginPrunesUsingChronologicalOrderRegardlessOfSessionIdOrInsertionOrder(): void
    {
        // The primary key on (user_id, session_id) gives SQLite a ready-made index for the
        // "WHERE user_id = ?" filter, so if the orderBy() clause were ever dropped, rows would
        // come back sorted by session_id instead of created_at. Session ids are chosen here so
        // that alphabetical order and chronological order disagree, which pins down that the
        // actual deleted record is selected via the created_at ordering.
        $config = ModuleConfig::fromArray([
            'enableSessionHistory' => true,
            'numberSessionHistory' => 3,
        ]);
        $user = $this->createUser('frank', 'frank@example.com');
        $userId = (int) $user->getId();
        $now = time();

        $this->insertSession($userId, 'aaa', $now + 500);
        $this->insertSession($userId, 'bbb', $now - 100000);
        $this->insertSession($userId, 'ccc', $now + 300);

        $decorator = new UserSessionHistoryDecorator(new EventCollector(), $config, new FakeSession('zzz'));
        $decorator->registerLogin($user);

        $rows = UserSessionHistory::query()->where(['user_id' => $userId])->all();
        $sessionIds = array_map(static fn (UserSessionHistory $row): string => $row->getSessionId(), $rows);
        sort($sessionIds);
        self::assertSame(['bbb', 'ccc', 'zzz'], $sessionIds);
    }

    public function testRegisterLoginUsesEmptySessionIdWhenSessionIsNull(): void
    {
        $config = ModuleConfig::fromArray([
            'enableSessionHistory' => true,
            'numberSessionHistory' => false,
        ]);
        $user = $this->createUser('erin', 'erin@example.com');
        $userId = (int) $user->getId();

        $decorator = new UserSessionHistoryDecorator(new EventCollector(), $config, null);
        $decorator->registerLogin($user);

        $rows = UserSessionHistory::query()->where(['user_id' => $userId])->all();
        self::assertCount(1, $rows);
        self::assertSame('', $rows[0]->getSessionId());
    }

    private function createUser(string $username, string $email): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('authkey');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }

    private function insertSession(int $userId, string $sessionId, int $createdAt): void
    {
        $session = new UserSessionHistory();
        $session->setUserId($userId);
        $session->setSessionId($sessionId);
        $session->setIp('127.0.0.1');
        $session->setCreatedAt($createdAt);
        $session->setUpdatedAt($createdAt);
        $session->save();
    }
}

final class EventCollector implements EventDispatcherInterface
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

final class FakeSession implements SessionInterface
{
    private bool $active = true;

    public function __construct(private string $id = 'test-session')
    {
    }

    #[\Override]
    public function all(): array
    {
        return [];
    }

    #[\Override]
    public function clear(): void
    {
    }

    #[\Override]
    public function close(): void
    {
        $this->active = false;
    }

    #[\Override]
    public function destroy(): void
    {
        $this->active = false;
    }

    #[\Override]
    public function discard(): void
    {
    }

    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    #[\Override]
    public function getCookieParameters(): array
    {
        return [];
    }

    #[\Override]
    public function getId(): ?string
    {
        return $this->id;
    }

    #[\Override]
    public function getName(): string
    {
        return 'TESTSESSID';
    }

    #[\Override]
    public function has(string $key): bool
    {
        return false;
    }

    #[\Override]
    public function isActive(): bool
    {
        return $this->active;
    }

    #[\Override]
    public function open(): void
    {
        $this->active = true;
    }

    #[\Override]
    public function pull(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    #[\Override]
    public function regenerateId(): void
    {
        $this->id = 'test-session-' . uniqid('', true);
    }

    #[\Override]
    public function remove(string $key): void
    {
    }

    #[\Override]
    public function set(string $key, mixed $value): void
    {
    }

    #[\Override]
    public function setId(string $sessionId): void
    {
        $this->id = $sessionId;
    }
}
