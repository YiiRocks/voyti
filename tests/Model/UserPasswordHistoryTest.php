<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use YiiRocks\Voyti\Model\UserPasswordHistory;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Dsn;

final class UserPasswordHistoryTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        $connection = $this->createSqliteConnection();
        ConnectionProvider::set($connection);
        $this->connection = $connection;

        $this->connection->createCommand('
            CREATE TABLE "user_password_history" (
                "user_id" INTEGER NOT NULL,
                "password_hash" VARCHAR(255) NOT NULL,
                "created_at" INTEGER NOT NULL,
                PRIMARY KEY ("user_id", "password_hash")
            )
        ')->execute();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_password_history"')->execute();
        }
        ConnectionProvider::clear();
        $this->connection = null;
    }

    /**
     * @return iterable<string, array{string, string, int|string}>
     */
    public static function getterSetterProvider(): iterable
    {
        yield 'passwordHash' => ['setPasswordHash', 'getPasswordHash', 'hash123'];
        yield 'createdAt' => ['setCreatedAt', 'getCreatedAt', 5000];
        yield 'userId' => ['setUserId', 'getUserId', 42];
    }

    public function testDefaultValues(): void
    {
        $entity = new UserPasswordHistory();
        self::assertSame(0, $entity->getUserId());
        self::assertSame('', $entity->getPasswordHash());
        self::assertSame(0, $entity->getCreatedAt());
    }

    public function testDeleteAllByUserIdRemovesOnlyThatUsersHistory(): void
    {
        $entry1 = new UserPasswordHistory();
        $entry1->setUserId(1);
        $entry1->setPasswordHash('hash1');
        $entry1->setCreatedAt(time());
        $entry1->save();

        $entry2 = new UserPasswordHistory();
        $entry2->setUserId(2);
        $entry2->setPasswordHash('hash2');
        $entry2->setCreatedAt(time());
        $entry2->save();

        UserPasswordHistory::deleteAllByUserId(1);

        self::assertCount(0, UserPasswordHistory::findByUserId(1));
        self::assertCount(1, UserPasswordHistory::findByUserId(2));
    }

    public function testFindByUserIdOrdersByCreatedAtDescending(): void
    {
        // Hash values are chosen so that alphabetical (primary-key) order and insertion
        // order both put "aaa-hash" first - the opposite of the expected DESC-by-time
        // order - so removing the ORDER BY clause is guaranteed to be observable here.
        $older = new UserPasswordHistory();
        $older->setUserId(1);
        $older->setPasswordHash('aaa-hash');
        $older->setCreatedAt(1000);
        $older->save();

        $newer = new UserPasswordHistory();
        $newer->setUserId(1);
        $newer->setPasswordHash('bbb-hash');
        $newer->setCreatedAt(2000);
        $newer->save();

        $found = UserPasswordHistory::findByUserId(1);

        self::assertCount(2, $found);
        self::assertSame('bbb-hash', $found[0]->getPasswordHash());
        self::assertSame('aaa-hash', $found[1]->getPasswordHash());
    }

    #[DataProvider('getterSetterProvider')]
    public function testGetSetProperty(string $setter, string $getter, int|string $value): void
    {
        $entity = new UserPasswordHistory();
        $entity->$setter($value);
        self::assertSame($value, $entity->$getter());
    }

    public function testPrimaryKey(): void
    {
        $entity = new UserPasswordHistory();
        self::assertSame(['user_id', 'password_hash'], $entity->primaryKey());
    }

    private function createSqliteConnection(): ConnectionInterface
    {
        $dsn = new Dsn('sqlite', ':memory:');
        $driver = new Driver($dsn);
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('set')->willReturn(true);
        $cache->method('get')->willReturn(null);
        $schemaCache = new SchemaCache($cache);
        $schemaCache->setEnabled(false);
        return new Connection($driver, $schemaCache);
    }
}
