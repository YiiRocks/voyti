<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Entity;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserSocialAccountTest extends TestCase
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
            CREATE TABLE "user_social_account" (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT,
                "user_id" INTEGER,
                "provider" VARCHAR(255) NOT NULL,
                "client_id" VARCHAR(255) NOT NULL,
                "code" VARCHAR(32),
                "email" VARCHAR(255),
                "username" VARCHAR(255),
                "data" TEXT,
                "created_at" INTEGER NOT NULL
            )
        ')->execute();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_social_account"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user"')->execute();
        }
        ConnectionProvider::clear();
        $this->connection = null;
    }

    public function testConnectWithNonPersistedUser(): void
    {
        $user = new User();
        $user->setUsername('temp');
        $user->setEmail('temp@test.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(1000);
        $user->setUpdatedAt(1000);

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('abc');
        $account->setCreatedAt(1000);

        $result = $account->connect($user);

        self::assertTrue($result);
        self::assertSame(0, $account->getUserId());
        self::assertNull($account->getUsername());
        self::assertNull($account->getEmail());
        self::assertNull($account->getCode());
    }

    public function testConnectWithUserHavingId(): void
    {
        $this->connection->createCommand()->insert('user', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password_hash' => 'hash',
            'auth_key' => 'key',
            'created_at' => 1000,
            'updated_at' => 1000,
        ])->execute();

        $loadedUser = User::query()->where(['username' => 'testuser'])->one();
        self::assertNotNull($loadedUser);

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('abc');
        $account->setCreatedAt(1000);
        $account->setUsername('olduser');
        $account->setEmail('old@test.com');
        $account->setCode('oldcode');

        $result = $account->connect($loadedUser);

        self::assertTrue($result);
        self::assertSame((int) $loadedUser->getId(), $account->getUserId());
        self::assertNull($account->getUsername());
        self::assertNull($account->getEmail());
        self::assertNull($account->getCode());

        $saved = UserSocialAccount::query()->where(['user_id' => $account->getUserId()])->one();
        self::assertNotNull($saved);
        self::assertNull($saved->getUsername());
        self::assertNull($saved->getEmail());
        self::assertNull($saved->getCode());
    }

    public function testDefaultValues(): void
    {
        $entity = new UserSocialAccount();
        self::assertSame('', $entity->getClientId());
        self::assertSame('', $entity->getProvider());
        self::assertSame(0, $entity->getCreatedAt());
    }

    public function testGetDecodedDataCachesResult(): void
    {
        $entity = new UserSocialAccount();
        $entity->setData('{"key":"val"}');

        $first = $entity->getDecodedData();
        $second = $entity->getDecodedData();

        self::assertSame($first, $second);
    }

    public function testGetDecodedDataReturnsDecodedArray(): void
    {
        $entity = new UserSocialAccount();
        $entity->setData('{"name":"John","age":30}');
        $decoded = $entity->getDecodedData();

        self::assertIsArray($decoded);
        self::assertSame('John', $decoded['name']);
        self::assertSame(30, $decoded['age']);
    }

    public function testGetDecodedDataReturnsNullForInvalidJson(): void
    {
        $entity = new UserSocialAccount();
        $entity->setData('{invalid json}');
        self::assertNull($entity->getDecodedData());
    }

    public function testGetDecodedDataReturnsNullWhenNoData(): void
    {
        $entity = new UserSocialAccount();
        self::assertNull($entity->getDecodedData());
    }

    public function testGetSetId(): void
    {
        $entity = new UserSocialAccount();
        $entity->setUserId(42);
        $entity->setProvider('github');
        $entity->setClientId('abc123');
        $entity->setCode('code123');
        $entity->setEmail('user@example.com');
        $entity->setUsername('githubuser');
        $entity->setCreatedAt(1000);
        $entity->setData('{"key":"val"}');

        self::assertSame(42, $entity->getUserId());
        self::assertSame('github', $entity->getProvider());
        self::assertSame('abc123', $entity->getClientId());
        self::assertSame('code123', $entity->getCode());
        self::assertSame('user@example.com', $entity->getEmail());
        self::assertSame('githubuser', $entity->getUsername());
        self::assertSame(1000, $entity->getCreatedAt());
        self::assertSame('{"key":"val"}', $entity->getData());
    }

    public function testGetSetWithNullValues(): void
    {
        $entity = new UserSocialAccount();
        self::assertNull($entity->getUserId());
        self::assertNull($entity->getCode());
        self::assertNull($entity->getEmail());
        self::assertNull($entity->getUsername());
        self::assertNull($entity->getData());
        self::assertNull($entity->getId());

        $entity->setUserId(null);
        $entity->setCode(null);
        $entity->setEmail(null);
        $entity->setUsername(null);
        $entity->setData(null);

        self::assertNull($entity->getUserId());
        self::assertNull($entity->getCode());
        self::assertNull($entity->getEmail());
        self::assertNull($entity->getUsername());
        self::assertNull($entity->getData());
    }

    public function testGetUserReturnsNullWhenNoUser(): void
    {
        $account = new UserSocialAccount();
        self::assertNull($account->getUser());
    }

    public function testGetUserReturnsUserWhenLinked(): void
    {
        $this->connection->createCommand()->insert('user', [
            'username' => 'testuser2',
            'email' => 'test2@example.com',
            'password_hash' => 'hash',
            'auth_key' => 'key',
            'created_at' => 1000,
            'updated_at' => 1000,
        ])->execute();

        $user = User::query()->where(['username' => 'testuser2'])->one();
        self::assertNotNull($user);

        $account = new UserSocialAccount();
        $account->setUserId((int) $user->getId());

        $found = $account->getUser();
        self::assertNotNull($found);
        self::assertSame($user->getId(), $found->getId());
    }

    public function testIsConnectedWithNullUserId(): void
    {
        $entity = new UserSocialAccount();
        self::assertFalse($entity->isConnected());
    }

    public function testIsConnectedWithUserId(): void
    {
        $entity = new UserSocialAccount();
        $entity->setUserId(5);
        self::assertTrue($entity->isConnected());
    }

    public function testSetDataResetsDecodedCache(): void
    {
        $entity = new UserSocialAccount();
        $entity->setData('{"key":"val"}');
        self::assertSame(['key' => 'val'], $entity->getDecodedData());

        $entity->setData('{"new":"data"}');
        self::assertSame(['new' => 'data'], $entity->getDecodedData());
    }

    public function testSetDataWithNullResetsDecodedCache(): void
    {
        $entity = new UserSocialAccount();
        $entity->setData('{"key":"val"}');
        self::assertSame(['key' => 'val'], $entity->getDecodedData());

        $entity->setData(null);
        self::assertNull($entity->getDecodedData());
    }

    public function testTableName(): void
    {
        $entity = new UserSocialAccount();
        self::assertSame('{{%user_social_account}}', $entity->tableName());
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
