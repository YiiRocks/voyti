<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\AuditLog;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

final class AuditLogTest extends TestCase
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
            CREATE TABLE "audit_log" (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT,
                "actor_user_id" INTEGER,
                "target_user_id" INTEGER,
                "target_name" VARCHAR(255),
                "action" VARCHAR(64) NOT NULL,
                "context" TEXT,
                "created_at" INTEGER NOT NULL
            )
        ')->execute();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->createCommand('DROP TABLE IF EXISTS "audit_log"')->execute();
        }
        ConnectionProvider::clear();
        $this->connection = null;
    }

    /**
     * @return iterable<string, array{string, string, int|string}>
     */
    public static function getterSetterProvider(): iterable
    {
        yield 'action' => ['setAction', 'getAction', 'user.create'];
        yield 'createdAt' => ['setCreatedAt', 'getCreatedAt', 5000];
    }

    public function testDefaultValues(): void
    {
        $entity = new AuditLog();
        self::assertSame('', $entity->getAction());
        self::assertNull($entity->getActorUserId());
        self::assertNull($entity->getContext());
        self::assertSame(0, $entity->getCreatedAt());
        self::assertNull($entity->getId());
        self::assertNull($entity->getTargetName());
        self::assertNull($entity->getTargetUserId());
    }

    public function testGetSetActorUserId(): void
    {
        $entity = new AuditLog();
        $entity->setActorUserId(5);
        self::assertSame(5, $entity->getActorUserId());
    }

    public function testGetSetContext(): void
    {
        $entity = new AuditLog();
        $entity->setContext('{"foo":"bar"}');
        self::assertSame('{"foo":"bar"}', $entity->getContext());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getterSetterProvider')]
    public function testGetSetProperty(string $setter, string $getter, int|string $value): void
    {
        $entity = new AuditLog();
        $entity->$setter($value);
        self::assertSame($value, $entity->$getter());
    }

    public function testGetSetTargetName(): void
    {
        $entity = new AuditLog();
        $entity->setTargetName('editor');
        self::assertSame('editor', $entity->getTargetName());
    }

    public function testGetSetTargetUserId(): void
    {
        $entity = new AuditLog();
        $entity->setTargetUserId(7);
        self::assertSame(7, $entity->getTargetUserId());
    }

    public function testPrimaryKey(): void
    {
        $entity = new AuditLog();
        self::assertSame(['id'], $entity->primaryKey());
    }

    public function testSearchFiltersByAction(): void
    {
        $this->createLog(1, 2, 'user.create');
        $this->createLog(1, 2, 'user.delete');

        $found = AuditLog::search(['action' => 'create'])->all();

        self::assertCount(1, $found);
        self::assertSame('user.create', $found[0]->getAction());
    }

    public function testSearchFiltersByActorUserId(): void
    {
        $this->createLog(1, 2, 'user.create');
        $this->createLog(3, 2, 'user.create');

        $found = AuditLog::search(['actor_user_id' => 1])->all();

        self::assertCount(1, $found);
        self::assertSame(1, $found[0]->getActorUserId());
    }

    public function testSearchFiltersByTargetUserId(): void
    {
        $this->createLog(1, 2, 'user.create');
        $this->createLog(1, 3, 'user.create');

        $found = AuditLog::search(['target_user_id' => 2])->all();

        self::assertCount(1, $found);
        self::assertSame(2, $found[0]->getTargetUserId());
    }

    public function testSearchWithoutFiltersOrdersByCreatedAtDescending(): void
    {
        $this->createLog(1, 2, 'user.create', 1000);
        $this->createLog(1, 2, 'user.delete', 2000);

        $found = AuditLog::search()->all();

        self::assertCount(2, $found);
        self::assertSame('user.delete', $found[0]->getAction());
        self::assertSame('user.create', $found[1]->getAction());
    }

    public function testTableName(): void
    {
        $entity = new AuditLog();
        self::assertSame('{{%audit_log}}', $entity->tableName());
    }

    private function createLog(int $actorUserId, int $targetUserId, string $action, ?int $createdAt = null): void
    {
        $log = new AuditLog();
        $log->setActorUserId($actorUserId);
        $log->setTargetUserId($targetUserId);
        $log->setAction($action);
        $log->setCreatedAt($createdAt ?? time());
        $log->save();
    }

    private function createSqliteConnection(): ConnectionInterface
    {
        $dsn = new \Yiisoft\Db\Sqlite\Dsn('sqlite', ':memory:');
        $driver = new \Yiisoft\Db\Sqlite\Driver($dsn);
        $cache = $this->createStub(\Psr\SimpleCache\CacheInterface::class);
        $cache->method('set')->willReturn(true);
        $cache->method('get')->willReturn(null);
        $schemaCache = new \Yiisoft\Db\Cache\SchemaCache($cache);
        $schemaCache->setEnabled(false);
        return new \Yiisoft\Db\Sqlite\Connection($driver, $schemaCache);
    }
}
