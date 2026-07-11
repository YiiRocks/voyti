<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Dsn;

final class UserProfileTest extends TestCase
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
            CREATE TABLE "user_profile" (
                "user_id" INTEGER NOT NULL,
                "bio" TEXT,
                "gravatar_email" VARCHAR(255),
                "location" VARCHAR(255),
                "name" VARCHAR(255),
                "public_email" VARCHAR(255),
                "timezone" VARCHAR(40),
                "website" VARCHAR(255)
            )
        ')->execute();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_profile"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user"')->execute();
        }
        ConnectionProvider::clear();
        $this->connection = null;
    }

    /**
     * @return iterable<string, array{string, string, int|string}>
     */
    public static function getterSetterProvider(): iterable
    {
        yield 'bio' => ['setBio', 'getBio', 'My bio'];
        yield 'gravatarEmail' => ['setGravatarEmail', 'getGravatarEmail', 'gravatar@example.com'];
        yield 'location' => ['setLocation', 'getLocation', 'New York'];
        yield 'name' => ['setName', 'getName', 'John Doe'];
        yield 'publicEmail' => ['setPublicEmail', 'getPublicEmail', 'public@example.com'];
        yield 'timezone' => ['setTimezone', 'getTimezone', 'America/New_York'];
        yield 'userId' => ['setUserId', 'getUserId', 42];
        yield 'website' => ['setWebsite', 'getWebsite', 'https://example.com'];
    }

    public function testFindByUserIdReturnsMatchingProfileAmongMultiple(): void
    {
        $profile1 = new UserProfile();
        $profile1->setUserId(1);
        $profile1->setName('Alice');
        $profile1->save();

        $profile2 = new UserProfile();
        $profile2->setUserId(2);
        $profile2->setName('Bob');
        $profile2->save();

        $found = UserProfile::findByUserId(2);

        self::assertNotNull($found);
        self::assertSame('Bob', $found->getName());
    }

    public function testFindByUserIdReturnsNullWhenNoneExists(): void
    {
        self::assertNull(UserProfile::findByUserId(1));
    }

    public function testGetGravatarIdFallsBackToUserEmail(): void
    {
        $this->connection->createCommand()->insert('user', [
            'username' => 'gravataruser',
            'email' => 'useremail@example.com',
            'password_hash' => 'hash',
            'auth_key' => 'key',
            'created_at' => 1000,
            'updated_at' => 1000,
        ])->execute();

        $user = User::query()->where(['username' => 'gravataruser'])->one();
        self::assertNotNull($user);

        $entity = new UserProfile();
        $entity->setUserId((int) $user->getId());

        $expected = hash('sha256', strtolower(trim('useremail@example.com')));
        self::assertSame($expected, $entity->getGravatarId());
    }

    public function testGetGravatarIdReturnsNullWhenNoEmailAndNoUserId(): void
    {
        $entity = new UserProfile();
        self::assertNull($entity->getGravatarId());
    }

    public function testGetGravatarIdReturnsNullWhenNoEmailAndUserTableMissing(): void
    {
        $entity = new UserProfile();
        $entity->setUserId(1);
        self::assertNull($entity->getGravatarId());
    }

    public function testGetGravatarIdReturnsNullWhenUserTableDropped(): void
    {
        $schema = $this->connection->getSchema();
        $schema->getTableSchema('{{%user}}', true);

        $this->connection->createCommand('DROP TABLE IF EXISTS "user"')->execute();
        $entity = new UserProfile();
        $entity->setUserId(1);
        self::assertNull($entity->getGravatarId());
    }

    public function testGetGravatarIdTrimsEmail(): void
    {
        $entity = new UserProfile();
        $entity->setGravatarEmail('  Test@Example.com  ');
        $expected = hash('sha256', 'test@example.com');
        self::assertSame($expected, $entity->getGravatarId());
    }

    public function testGetGravatarIdWithCachedNullSchemaReturnsHash(): void
    {
        $this->connection->createCommand('DROP TABLE IF EXISTS "user"')->execute();

        $schema = $this->connection->getSchema();
        $schema->getTableSchema('{{%user}}', true);

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

        $this->connection->createCommand()->insert('user', [
            'username' => 'cacheduser',
            'email' => 'cached@example.com',
            'password_hash' => 'hash',
            'auth_key' => 'key',
            'created_at' => 1000,
            'updated_at' => 1000,
        ])->execute();

        $entity = new UserProfile();
        $entity->setUserId(1);
        $expected = hash('sha256', strtolower(trim('cached@example.com')));
        self::assertSame($expected, $entity->getGravatarId());
    }

    public function testGetGravatarIdWithEmptyGravatarEmailFallsBackToPublicEmail(): void
    {
        $entity = new UserProfile();
        $entity->setGravatarEmail('');
        $entity->setPublicEmail('Public@Example.com');
        $expected = hash('sha256', strtolower(trim('Public@Example.com')));
        self::assertSame($expected, $entity->getGravatarId());
    }

    public function testGetGravatarIdWithGravatarEmail(): void
    {
        $entity = new UserProfile();
        $entity->setGravatarEmail('Test@Example.com');
        $expected = hash('sha256', strtolower(trim('Test@Example.com')));
        self::assertSame($expected, $entity->getGravatarId());
    }

    public function testGetGravatarIdWithNullGravatarEmailFallsBackToPublicEmail(): void
    {
        $entity = new UserProfile();
        $entity->setPublicEmail('public@example.com');
        $expected = hash('sha256', strtolower(trim('public@example.com')));
        self::assertSame($expected, $entity->getGravatarId());
    }

    public function testGetGravatarIdWithPublicEmailPrecedence(): void
    {
        $entity = new UserProfile();
        $entity->setPublicEmail('public@example.com');
        $entity->setGravatarEmail(null);
        $expected = hash('sha256', strtolower(trim('public@example.com')));
        self::assertSame($expected, $entity->getGravatarId());
    }

    public function testGetGravatarUrlReturnsNullWhenNoGravatarId(): void
    {
        $entity = new UserProfile();
        self::assertNull($entity->getGravatarUrl());
    }

    public function testGetGravatarUrlUsesCustomSize(): void
    {
        $entity = new UserProfile();
        $entity->setGravatarEmail('test@example.com');
        $id = hash('sha256', 'test@example.com');
        self::assertSame("https://www.gravatar.com/avatar/{$id}?s=64&d=mp", $entity->getGravatarUrl(64));
    }

    public function testGetGravatarUrlUsesDefaultSize(): void
    {
        $entity = new UserProfile();
        $entity->setGravatarEmail('test@example.com');
        $id = hash('sha256', 'test@example.com');
        self::assertSame("https://www.gravatar.com/avatar/{$id}?s=256&d=mp", $entity->getGravatarUrl());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getterSetterProvider')]
    public function testGetSetProperty(string $setter, string $getter, int|string $value): void
    {
        $entity = new UserProfile();
        $entity->$setter($value);
        self::assertSame($value, $entity->$getter());
    }

    public function testGetUserReturnsNullWhenNotLinked(): void
    {
        $entity = new UserProfile();
        self::assertNull($entity->getUser());
    }

    public function testGetUserReturnsUserWhenLinked(): void
    {
        $this->connection->createCommand()->insert('user', [
            'username' => 'profileuser',
            'email' => 'profileuser@example.com',
            'password_hash' => 'hash',
            'auth_key' => 'key',
            'created_at' => 1000,
            'updated_at' => 1000,
        ])->execute();

        $user = User::query()->where(['username' => 'profileuser'])->one();
        self::assertNotNull($user);

        $entity = new UserProfile();
        $entity->setUserId((int) $user->getId());

        $found = $entity->getUser();
        self::assertNotNull($found);
        self::assertSame($user->getId(), $found->getId());
    }

    public function testTableName(): void
    {
        $entity = new UserProfile();
        self::assertSame('{{%user_profile}}', $entity->tableName());
    }

    private function createSqliteConnection(): ConnectionInterface
    {
        $dsn = new Dsn('sqlite', ':memory:');
        $driver = new Driver($dsn);
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('set')->willReturn(true);
        $cache->method('get')->willReturn(null);
        $schemaCache = new SchemaCache($cache);
        return new SqliteConnection($driver, $schemaCache);
    }
}
