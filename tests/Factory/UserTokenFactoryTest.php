<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Factory;

use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserTokenFactoryTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
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
        $factory = new UserTokenFactory();
        $rawCode = $factory->makeConfirmationToken(42);

        self::assertNotEmpty($rawCode);
        self::assertSame(32, strlen($rawCode));

        $saved = UserToken::findByUserIdAndCodeAndType(42, $rawCode, UserToken::TYPE_CONFIRMATION);
        self::assertNotNull($saved);
        self::assertSame(42, $saved->getUserId());
        self::assertSame(UserToken::TYPE_CONFIRMATION, $saved->getType());
        self::assertGreaterThan(0, $saved->getCreatedAt());
        self::assertNotSame($rawCode, $saved->getCode());

        $storedAsRaw = (new ActiveQuery(new UserToken()))
            ->where(['code' => $rawCode])
            ->one();
        self::assertNull($storedAsRaw);
    }

    public function testMakeConfirmNewMailToken(): void
    {
        $factory = new UserTokenFactory();
        $rawCode = $factory->makeConfirmNewMailToken(7);

        self::assertNotEmpty($rawCode);
        self::assertSame(32, strlen($rawCode));

        $saved = UserToken::findByUserIdAndCodeAndType(7, $rawCode, UserToken::TYPE_CONFIRM_NEW_EMAIL);
        self::assertNotNull($saved);
        self::assertSame(7, $saved->getUserId());
        self::assertSame(UserToken::TYPE_CONFIRM_NEW_EMAIL, $saved->getType());
        self::assertGreaterThan(0, $saved->getCreatedAt());
    }

    public function testMakeConfirmOldMailToken(): void
    {
        $factory = new UserTokenFactory();
        $rawCode = $factory->makeConfirmOldMailToken(99);

        self::assertNotEmpty($rawCode);
        self::assertSame(32, strlen($rawCode));

        $saved = UserToken::findByUserIdAndCodeAndType(99, $rawCode, UserToken::TYPE_CONFIRM_OLD_EMAIL);
        self::assertNotNull($saved);
        self::assertSame(99, $saved->getUserId());
        self::assertSame(UserToken::TYPE_CONFIRM_OLD_EMAIL, $saved->getType());
        self::assertGreaterThan(0, $saved->getCreatedAt());
    }

    public function testMakeRecoveryToken(): void
    {
        $factory = new UserTokenFactory();
        $rawCode = $factory->makeRecoveryToken(1);

        self::assertNotEmpty($rawCode);
        self::assertSame(32, strlen($rawCode));

        $saved = UserToken::findByUserIdAndCodeAndType(1, $rawCode, UserToken::TYPE_RECOVERY);
        self::assertNotNull($saved);
        self::assertSame(1, $saved->getUserId());
        self::assertSame(UserToken::TYPE_RECOVERY, $saved->getType());
        self::assertGreaterThan(0, $saved->getCreatedAt());
    }
}
