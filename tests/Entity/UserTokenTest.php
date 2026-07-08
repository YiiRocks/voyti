<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Entity;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserTokenTest extends TestCase
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
            CREATE TABLE "user" (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT,
                "username" VARCHAR(255) NOT NULL,
                "email" VARCHAR(255) NOT NULL,
                "password_hash" VARCHAR(255) NOT NULL,
                "auth_key" VARCHAR(32) NOT NULL,
                "auth_tf_enabled" INTEGER NOT NULL DEFAULT 0,
                "auth_tf_key" VARCHAR(64),
                "auth_tf_type" VARCHAR(20),
                "blocked_at" INTEGER,
                "confirmed_at" INTEGER,
                "created_at" INTEGER NOT NULL,
                "flags" INTEGER NOT NULL DEFAULT 0,
                "gdpr_consent" INTEGER NOT NULL DEFAULT 0,
                "gdpr_consent_date" INTEGER,
                "gdpr_deleted" INTEGER NOT NULL DEFAULT 0,
                "last_login_at" INTEGER,
                "last_login_ip" VARCHAR(45),
                "password_changed_at" INTEGER,
                "registration_ip" VARCHAR(45),
                "unconfirmed_email" VARCHAR(255),
                "updated_at" INTEGER NOT NULL
            )
        ')->execute();

        $this->connection->createCommand('
            CREATE TABLE "user_token" (
                "user_id" INTEGER NOT NULL,
                "code" VARCHAR(32) NOT NULL,
                "type" SMALLINT NOT NULL,
                "created_at" INTEGER NOT NULL
            )
        ')->execute();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_token"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user"')->execute();
        }
        ConnectionProvider::clear();
        $this->connection = null;
    }

    public function testConstants(): void
    {
        self::assertSame(0, UserToken::TYPE_CONFIRMATION);
        self::assertSame(1, UserToken::TYPE_RECOVERY);
        self::assertSame(2, UserToken::TYPE_CONFIRM_NEW_EMAIL);
        self::assertSame(3, UserToken::TYPE_CONFIRM_OLD_EMAIL);
    }

    public function testDefaultValues(): void
    {
        $entity = new UserToken();
        self::assertSame(0, $entity->getUserId());
        self::assertSame('', $entity->getCode());
        self::assertSame(0, $entity->getType());
        self::assertSame(0, $entity->getCreatedAt());
    }

    public function testGetIsExpiredDefaultLifespanForConfirmationBoundary(): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(time() - 86401);
        $entity->setType(UserToken::TYPE_CONFIRMATION);

        self::assertTrue($entity->getIsExpired());
    }

    public function testGetIsExpiredDefaultLifespanForConfirmationEdgeCase(): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(time() - 86400);
        $entity->setType(UserToken::TYPE_CONFIRMATION);

        self::assertFalse($entity->getIsExpired());
    }

    public function testGetIsExpiredDefaultLifespanForConfirmationExpired(): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(time() - 100000);
        $entity->setType(UserToken::TYPE_CONFIRMATION);

        self::assertTrue($entity->getIsExpired());
    }

    public function testGetIsExpiredDefaultLifespanForConfirmationNotExpired(): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(time());
        $entity->setType(UserToken::TYPE_CONFIRMATION);

        self::assertFalse($entity->getIsExpired());
    }

    public function testGetIsExpiredDefaultLifespanForConfirmNewEmail(): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(time() - 100000);
        $entity->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);

        self::assertTrue($entity->getIsExpired());
    }

    public function testGetIsExpiredDefaultLifespanForConfirmOldEmail(): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(time() - 100000);
        $entity->setType(UserToken::TYPE_CONFIRM_OLD_EMAIL);

        self::assertTrue($entity->getIsExpired());
    }

    public function testGetIsExpiredDefaultLifespanForRecoveryBoundary(): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(time() - 21601);
        $entity->setType(UserToken::TYPE_RECOVERY);

        self::assertTrue($entity->getIsExpired());
    }

    public function testGetIsExpiredDefaultLifespanForRecoveryEdgeCase(): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(time() - 21600);
        $entity->setType(UserToken::TYPE_RECOVERY);

        self::assertFalse($entity->getIsExpired());
    }

    public function testGetIsExpiredDefaultLifespanForRecoveryExpired(): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(time() - 50000);
        $entity->setType(UserToken::TYPE_RECOVERY);

        self::assertTrue($entity->getIsExpired());
    }

    public function testGetIsExpiredDefaultLifespanForRecoveryNotExpired(): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(time());
        $entity->setType(UserToken::TYPE_RECOVERY);

        self::assertFalse($entity->getIsExpired());
    }

    public function testGetIsExpiredWithCustomLifespanEdgeCase(): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(time() - 86400);
        $entity->setType(UserToken::TYPE_CONFIRMATION);

        self::assertFalse($entity->getIsExpired(86400));
    }

    public function testGetIsExpiredWithCustomLifespanExpired(): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(time() - 100);
        $entity->setType(UserToken::TYPE_CONFIRMATION);

        self::assertTrue($entity->getIsExpired(50));
    }

    public function testGetIsExpiredWithCustomLifespanNotExpired(): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(time());
        $entity->setType(UserToken::TYPE_CONFIRMATION);

        self::assertFalse($entity->getIsExpired(86400));
    }

    public function testGetSetCode(): void
    {
        $entity = new UserToken();
        $entity->setCode('abc123');
        self::assertSame('abc123', $entity->getCode());
    }

    public function testGetSetCreatedAt(): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(5000);
        self::assertSame(5000, $entity->getCreatedAt());
    }

    public function testGetSetType(): void
    {
        $entity = new UserToken();
        $entity->setType(UserToken::TYPE_CONFIRMATION);
        self::assertSame(UserToken::TYPE_CONFIRMATION, $entity->getType());
    }

    public function testGetSetUserId(): void
    {
        $entity = new UserToken();
        $entity->setUserId(42);
        self::assertSame(42, $entity->getUserId());
    }

    public function testGetUserReturnsNullWhenNoUser(): void
    {
        $entity = new UserToken();
        $entity->setUserId(999);

        self::assertNull($entity->getUser());
    }

    public function testGetUserReturnsUserWhenLinked(): void
    {
        $this->connection->createCommand()->insert('user', [
            'username' => 'tokenuser',
            'email' => 'tokenuser@example.com',
            'password_hash' => 'hash',
            'auth_key' => 'key',
            'created_at' => 1000,
            'updated_at' => 1000,
        ])->execute();

        $user = User::query()->where(['username' => 'tokenuser'])->one();
        self::assertNotNull($user);

        $entity = new UserToken();
        $entity->setUserId((int) $user->getId());

        $found = $entity->getUser();
        self::assertNotNull($found);
        self::assertSame($user->getId(), $found->getId());
    }

    public function testPrimaryKey(): void
    {
        $entity = new UserToken();
        self::assertSame(['user_id', 'code', 'type'], $entity->primaryKey());
    }

    public function testTableName(): void
    {
        $entity = new UserToken();
        self::assertSame('{{%user_token}}', $entity->tableName());
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
