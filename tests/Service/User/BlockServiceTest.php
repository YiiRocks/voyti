<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Service\User\BlockService;
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
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testRunBlocksUnblockedUserAndDispatchesTwoEvents(): void
    {
        $user = $this->createUser('alice');
        self::assertFalse($user->isBlocked());

        $dispatcher = new BlockEventCollector();
        $service = new BlockService($dispatcher);

        $before = time();
        $result = $service->run($user);
        $after = time();

        self::assertTrue($result);
        self::assertTrue($user->isBlocked());
        self::assertGreaterThanOrEqual($before, $user->getBlockedAt());
        self::assertLessThanOrEqual($after, $user->getBlockedAt());

        $reloaded = User::query()->findByPk($user->getId());
        self::assertTrue($reloaded->isBlocked());

        $events = $dispatcher->events();
        self::assertCount(2, $events);
        self::assertInstanceOf(UserEvent::class, $events[0]);
        self::assertInstanceOf(UserEvent::class, $events[1]);
        self::assertSame($user, $events[0]->getUser());
        self::assertSame($user, $events[1]->getUser());
    }

    public function testRunUnblocksBlockedUserAndDispatchesTwoEvents(): void
    {
        $user = $this->createUser('bob');
        $user->setBlockedAt(time());
        $user->save();
        self::assertTrue($user->isBlocked());

        $dispatcher = new BlockEventCollector();
        $service = new BlockService($dispatcher);

        $result = $service->run($user);

        self::assertTrue($result);
        self::assertFalse($user->isBlocked());
        self::assertNull($user->getBlockedAt());

        $reloaded = User::query()->findByPk($user->getId());
        self::assertFalse($reloaded->isBlocked());

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
