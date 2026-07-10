<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Entity;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use YiiRocks\Voyti\Entity\UserSessionHistory;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Db\Sqlite\Dsn;

final class UserSessionHistoryTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite extension required.');
        }

        $connection = $this->createSqliteConnection();
        ConnectionProvider::set($connection);
        $this->connection = $connection;

        $this->connection->createCommand('
            CREATE TABLE IF NOT EXISTS "user_session_history" (
                "user_id" INTEGER NOT NULL,
                "session_id" VARCHAR(64) NOT NULL,
                "ip" VARCHAR(45),
                "user_agent" TEXT,
                "created_at" INTEGER NOT NULL,
                "updated_at" INTEGER NOT NULL,
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

    public function testDefaultValues(): void
    {
        $entity = new UserSessionHistory();
        self::assertSame(0, $entity->getUserId());
        self::assertSame('', $entity->getSessionId());
        self::assertSame(0, $entity->getCreatedAt());
        self::assertSame(0, $entity->getUpdatedAt());
        self::assertNull($entity->getIp());
        self::assertNull($entity->getUserAgent());
    }

    public function testGetSetCreatedAt(): void
    {
        $entity = new UserSessionHistory();
        $entity->setCreatedAt(1000);
        self::assertSame(1000, $entity->getCreatedAt());
    }

    public function testGetSetIp(): void
    {
        $entity = new UserSessionHistory();
        $entity->setIp('192.168.1.1');
        self::assertSame('192.168.1.1', $entity->getIp());
    }

    public function testGetSetSessionId(): void
    {
        $entity = new UserSessionHistory();
        $entity->setSessionId('sess-abc-123');
        self::assertSame('sess-abc-123', $entity->getSessionId());
    }

    public function testGetSetUpdatedAt(): void
    {
        $entity = new UserSessionHistory();
        $entity->setUpdatedAt(2000);
        self::assertSame(2000, $entity->getUpdatedAt());
    }

    public function testGetSetUserAgent(): void
    {
        $entity = new UserSessionHistory();
        $entity->setUserAgent('Mozilla/5.0');
        self::assertSame('Mozilla/5.0', $entity->getUserAgent());
    }

    public function testGetSetUserId(): void
    {
        $entity = new UserSessionHistory();
        $entity->setUserId(42);
        self::assertSame(42, $entity->getUserId());
    }

    public function testPrimaryKey(): void
    {
        $entity = new UserSessionHistory();
        self::assertSame(['user_id', 'session_id'], $entity->primaryKey());
    }

    public function testTableName(): void
    {
        $entity = new UserSessionHistory();
        self::assertSame('{{%user_session_history}}', $entity->tableName());
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
