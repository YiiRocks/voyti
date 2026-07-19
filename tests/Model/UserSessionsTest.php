<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use YiiRocks\Voyti\Model\UserSessions;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Db\Sqlite\Dsn;

final class UserSessionsTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        $connection = $this->createSqliteConnection();
        ConnectionProvider::set($connection);
        $this->connection = $connection;

        $this->connection->createCommand('
            CREATE TABLE IF NOT EXISTS "user_sessions" (
                "user_id" INTEGER NOT NULL,
                "session_id" VARCHAR(64) NOT NULL,
                "ip" VARCHAR(45),
                "user_agent" TEXT,
                "created_at" INTEGER NOT NULL,
                "updated_at" INTEGER NOT NULL,
                "revoked_at" INTEGER,
                PRIMARY KEY ("user_id", "session_id")
            )
        ')->execute();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
        }
    }

    /**
     * @return iterable<string, array{string, string, int|string}>
     */
    public static function getterSetterProvider(): iterable
    {
        yield 'createdAt' => ['setCreatedAt', 'getCreatedAt', 1000];
        yield 'ip' => ['setIp', 'getIp', '192.168.1.1'];
        yield 'revokedAt' => ['setRevokedAt', 'getRevokedAt', 3000];
        yield 'sessionId' => ['setSessionId', 'getSessionId', 'sess-abc-123'];
        yield 'updatedAt' => ['setUpdatedAt', 'getUpdatedAt', 2000];
        yield 'userAgent' => ['setUserAgent', 'getUserAgent', 'Mozilla/5.0'];
        yield 'userId' => ['setUserId', 'getUserId', 42];
    }

    public function testDefaultValues(): void
    {
        $entity = new UserSessions();
        self::assertSame(0, $entity->getUserId());
        self::assertSame('', $entity->getSessionId());
        self::assertSame(0, $entity->getCreatedAt());
        self::assertSame(0, $entity->getUpdatedAt());
        self::assertNull($entity->getIp());
        self::assertNull($entity->getUserAgent());
        self::assertNull($entity->getRevokedAt());
        self::assertFalse($entity->isRevoked());
    }

    public function testFindAllSessionsReturnsAll(): void
    {
        $this->createSession(1, 'sess-1', '203.0.113.1');
        $this->createSession(2, 'sess-2', '203.0.113.2');

        self::assertCount(2, UserSessions::findAllSessions());
    }

    public function testFindByUserIdAndSessionIdFiltersByBoth(): void
    {
        $this->createSession(1, 'sess-1', '203.0.113.1');
        $this->createSession(2, 'sess-2', '203.0.113.2');

        $found = UserSessions::findByUserIdAndSessionId(1, 'sess-1');
        self::assertNotNull($found);
        self::assertSame(1, $found->getUserId());
        self::assertSame('sess-1', $found->getSessionId());

        self::assertNull(UserSessions::findByUserIdAndSessionId(1, 'sess-2'));
        self::assertNull(UserSessions::findByUserIdAndSessionId(2, 'sess-1'));
    }

    public function testFindByUserIdFiltersByUserId(): void
    {
        $this->createSession(1, 'sess-1', '203.0.113.1');
        $this->createSession(1, 'sess-1b', '203.0.113.3');
        $this->createSession(2, 'sess-2', '203.0.113.2');

        $sessions = UserSessions::findByUserId(1);

        self::assertCount(2, $sessions);
    }

    #[DataProvider('getterSetterProvider')]
    public function testGetSetProperty(string $setter, string $getter, int|string $value): void
    {
        $entity = new UserSessions();
        $entity->$setter($value);
        self::assertSame($value, $entity->$getter());
    }

    public function testIsRevokedReflectsRevokedAt(): void
    {
        $entity = new UserSessions();
        self::assertFalse($entity->isRevoked());

        $entity->setRevokedAt(time());
        self::assertTrue($entity->isRevoked());
    }

    public function testPrimaryKey(): void
    {
        $entity = new UserSessions();
        self::assertSame(['user_id', 'session_id'], $entity->primaryKey());
    }

    public function testSearchWithIpFilter(): void
    {
        $this->createSession(1, 'sess-1', '203.0.113.1');
        $this->createSession(1, 'sess-2', '198.51.100.1');

        $sessions = UserSessions::search(['ip' => '203.0.113']);

        self::assertCount(1, $sessions);
    }

    public function testSearchWithNoFiltersReturnsAll(): void
    {
        $this->createSession(1, 'sess-1', '203.0.113.1');
        $this->createSession(2, 'sess-2', '203.0.113.2');

        self::assertCount(2, UserSessions::search());
    }

    public function testSearchWithUserIdFilter(): void
    {
        $this->createSession(1, 'sess-1', '203.0.113.1');
        $this->createSession(2, 'sess-2', '203.0.113.2');

        $sessions = UserSessions::search(['user_id' => 1]);

        self::assertCount(1, $sessions);
        self::assertSame('sess-1', $sessions[0]->getSessionId());
    }

    private function createSession(int $userId, string $sessionId, string $ip): UserSessions
    {
        $session = new UserSessions();
        $session->setUserId($userId);
        $session->setSessionId($sessionId);
        $session->setIp($ip);
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();

        return $session;
    }

    private function createSqliteConnection(): ConnectionInterface
    {
        $dsn = new Dsn('sqlite', ':memory:');
        $driver = new SqliteDriver($dsn);
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('set')->willReturn(true);
        $cache->method('get')->willReturn(null);
        $schemaCache = new SchemaCache($cache);
        $schemaCache->setEnabled(false);
        return new SqliteConnection($driver, $schemaCache);
    }
}
