<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\UserBackupCode;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserBackupCodeTest extends TestCase
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
            CREATE TABLE "user_backup_code" (
                "user_id" INTEGER NOT NULL,
                "code_hash" VARCHAR(255) NOT NULL,
                "used_at" INTEGER,
                "created_at" INTEGER NOT NULL,
                PRIMARY KEY ("user_id", "code_hash")
            )
        ')->execute();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_backup_code"')->execute();
        }
        ConnectionProvider::clear();
        $this->connection = null;
    }

    /**
     * @return iterable<string, array{string, string, int|string}>
     */
    public static function getterSetterProvider(): iterable
    {
        yield 'codeHash' => ['setCodeHash', 'getCodeHash', 'abc123'];
        yield 'createdAt' => ['setCreatedAt', 'getCreatedAt', 5000];
        yield 'userId' => ['setUserId', 'getUserId', 42];
    }

    public function testDefaultValues(): void
    {
        $entity = new UserBackupCode();
        self::assertSame(0, $entity->getUserId());
        self::assertSame('', $entity->getCodeHash());
        self::assertNull($entity->getUsedAt());
        self::assertSame(0, $entity->getCreatedAt());
    }

    public function testDeleteAllByUserIdRemovesOnlyThatUsersCodes(): void
    {
        $code1 = new UserBackupCode();
        $code1->setUserId(1);
        $code1->setCodeHash('hash1');
        $code1->setCreatedAt(time());
        $code1->save();

        $code2 = new UserBackupCode();
        $code2->setUserId(2);
        $code2->setCodeHash('hash2');
        $code2->setCreatedAt(time());
        $code2->save();

        UserBackupCode::deleteAllByUserId(1);

        self::assertCount(0, UserBackupCode::findUnusedByUserId(1));
        self::assertCount(1, UserBackupCode::findUnusedByUserId(2));
    }

    public function testFindUnusedByUserIdExcludesUsedCodes(): void
    {
        $unused = new UserBackupCode();
        $unused->setUserId(1);
        $unused->setCodeHash('unused-hash');
        $unused->setCreatedAt(time());
        $unused->save();

        $used = new UserBackupCode();
        $used->setUserId(1);
        $used->setCodeHash('used-hash');
        $used->setCreatedAt(time());
        $used->setUsedAt(time());
        $used->save();

        $found = UserBackupCode::findUnusedByUserId(1);

        self::assertCount(1, $found);
        self::assertSame('unused-hash', $found[0]->getCodeHash());
    }

    public function testFindUnusedByUserIdFiltersByUserId(): void
    {
        $code1 = new UserBackupCode();
        $code1->setUserId(1);
        $code1->setCodeHash('hash1');
        $code1->setCreatedAt(time());
        $code1->save();

        $code2 = new UserBackupCode();
        $code2->setUserId(2);
        $code2->setCodeHash('hash2');
        $code2->setCreatedAt(time());
        $code2->save();

        $found = UserBackupCode::findUnusedByUserId(1);
        self::assertCount(1, $found);
        self::assertSame(1, $found[0]->getUserId());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getterSetterProvider')]
    public function testGetSetProperty(string $setter, string $getter, int|string $value): void
    {
        $entity = new UserBackupCode();
        $entity->$setter($value);
        self::assertSame($value, $entity->$getter());
    }

    public function testGetSetUsedAt(): void
    {
        $entity = new UserBackupCode();
        $entity->setUsedAt(1234);
        self::assertSame(1234, $entity->getUsedAt());
    }

    public function testPrimaryKey(): void
    {
        $entity = new UserBackupCode();
        self::assertSame(['user_id', 'code_hash'], $entity->primaryKey());
    }

    public function testTableName(): void
    {
        $entity = new UserBackupCode();
        self::assertSame('{{%user_backup_code}}', $entity->tableName());
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
