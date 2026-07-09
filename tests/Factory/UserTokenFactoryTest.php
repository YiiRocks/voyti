<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Factory;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Dsn;

final class UserTokenFactoryTest extends TestCase
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
                "anonymized" INTEGER NOT NULL DEFAULT 0,
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
                "code" VARCHAR(64) NOT NULL,
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

    public function testMakeConfirmationToken(): void
    {
        $factory = new UserTokenFactory(new UserTokenRepository());
        $token = $factory->makeConfirmationToken(42);

        self::assertSame(42, $token->getUserId());
        self::assertSame(UserToken::TYPE_CONFIRMATION, $token->getType());
        self::assertNotEmpty($token->getCode());
        self::assertSame(32, strlen($token->getCode()));
        self::assertGreaterThan(0, $token->getCreatedAt());

        $saved = (new ActiveQuery(new UserToken()))
            ->where(['code' => $token->getCode()])
            ->one();
        self::assertNotNull($saved);
    }

    public function testMakeConfirmNewMailToken(): void
    {
        $factory = new UserTokenFactory(new UserTokenRepository());
        $token = $factory->makeConfirmNewMailToken(7);

        self::assertSame(7, $token->getUserId());
        self::assertSame(UserToken::TYPE_CONFIRM_NEW_EMAIL, $token->getType());
        self::assertNotEmpty($token->getCode());
        self::assertSame(32, strlen($token->getCode()));
        self::assertGreaterThan(0, $token->getCreatedAt());
    }

    public function testMakeConfirmOldMailToken(): void
    {
        $factory = new UserTokenFactory(new UserTokenRepository());
        $token = $factory->makeConfirmOldMailToken(99);

        self::assertSame(99, $token->getUserId());
        self::assertSame(UserToken::TYPE_CONFIRM_OLD_EMAIL, $token->getType());
        self::assertNotEmpty($token->getCode());
        self::assertSame(32, strlen($token->getCode()));
        self::assertGreaterThan(0, $token->getCreatedAt());
    }

    public function testMakeRecoveryToken(): void
    {
        $factory = new UserTokenFactory(new UserTokenRepository());
        $token = $factory->makeRecoveryToken(1);

        self::assertSame(1, $token->getUserId());
        self::assertSame(UserToken::TYPE_RECOVERY, $token->getType());
        self::assertNotEmpty($token->getCode());
        self::assertSame(32, strlen($token->getCode()));
        self::assertGreaterThan(0, $token->getCreatedAt());
    }

    public function testMultipleTokensHaveDifferentCodes(): void
    {
        $factory = new UserTokenFactory(new UserTokenRepository());
        $token1 = $factory->makeConfirmationToken(1);
        $token2 = $factory->makeConfirmationToken(1);

        self::assertNotSame($token1->getCode(), $token2->getCode());
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
        return new SqliteConnection($driver, $schemaCache);
    }
}
