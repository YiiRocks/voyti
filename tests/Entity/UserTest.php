<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Entity;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Entity\UserToken;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserTest extends TestCase
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

        $this->connection->createCommand('
            CREATE TABLE "user_token" (
                "user_id" INTEGER NOT NULL,
                "code" VARCHAR(32) NOT NULL,
                "type" SMALLINT NOT NULL,
                "created_at" INTEGER NOT NULL
            )
        ')->execute();

        $this->connection->createCommand('
            CREATE TABLE "user_session_history" (
                "user_id" INTEGER NOT NULL,
                "session_id" VARCHAR(255) NOT NULL,
                "user_agent" TEXT,
                "ip" VARCHAR(45) NOT NULL,
                "created_at" INTEGER NOT NULL,
                "updated_at" INTEGER NOT NULL
            )
        ')->execute();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_session_history"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_token"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_social_account"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_profile"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user"')->execute();
        }
        ConnectionProvider::clear();
        $this->connection = null;
    }

    public function testDefaultValues(): void
    {
        $entity = new User();
        self::assertNull($entity->getId());
        self::assertSame('', $entity->getEmail());
        self::assertSame('', $entity->getUsername());
        self::assertSame('', $entity->getPasswordHash());
        self::assertSame('', $entity->getAuthKey());
        self::assertSame(0, $entity->getCreatedAt());
        self::assertSame(0, $entity->getUpdatedAt());
        self::assertSame(0, $entity->getFlags());
        self::assertNull($entity->getRegistrationIp());
        self::assertNull($entity->getUnconfirmedEmail());
        self::assertNull($entity->getBlockedAt());
        self::assertNull($entity->getConfirmedAt());
        self::assertNull($entity->getLastLoginAt());
        self::assertNull($entity->getLastLoginIp());
        self::assertNull($entity->getPasswordChangedAt());
        self::assertNull($entity->getAuthTfKey());
        self::assertNull($entity->getAuthTfType());
        self::assertFalse($entity->isAuthTfEnabled());
        self::assertFalse($entity->isGdprConsent());
        self::assertFalse($entity->isAnonymized());
        self::assertNull($entity->getGdprConsentDate());
    }

    public function testGetCookieLoginKey(): void
    {
        $entity = new User();
        $entity->setAuthKey('cookie_key');
        self::assertSame('cookie_key', $entity->getCookieLoginKey());
    }

    public function testGetIdReturnsNullWhenNotSet(): void
    {
        $entity = new User();
        self::assertNull($entity->getId());
    }

    public function testGetIdReturnsStringWhenSet(): void
    {
        $entity = new User();
        $entity->setUsername('test');
        $entity->setEmail('test@example.com');
        $entity->setPasswordHash('hash');
        $entity->setAuthKey('key');
        $entity->setCreatedAt(1000);
        $entity->setUpdatedAt(1000);
        $entity->save();

        $id = $entity->getId();
        self::assertNotNull($id);
        self::assertIsString($id);
    }

    public function testGetPasswordAgeAtExactDay(): void
    {
        $entity = new User();
        $entity->setPasswordChangedAt(time() - 86400);
        $age = $entity->getPasswordAge();
        self::assertSame(1, $age);
    }

    public function testGetPasswordAgeJustUnderDay(): void
    {
        $entity = new User();
        $entity->setPasswordChangedAt(time() - 86399);
        $age = $entity->getPasswordAge();
        self::assertSame(0, $age);
    }

    public function testGetPasswordAgeWithCurrentTime(): void
    {
        $entity = new User();
        $entity->setPasswordChangedAt(time());
        self::assertSame(0, $entity->getPasswordAge());
    }

    public function testGetPasswordAgeWithNullPasswordChangedAt(): void
    {
        $entity = new User();
        $entity->setPasswordChangedAt(null);
        self::assertSame(9999, $entity->getPasswordAge());
    }

    public function testGetProfileReturnsNullWhenNotLinked(): void
    {
        $entity = new User();
        self::assertNull($entity->getProfile());
    }

    public function testGetProfileReturnsProfileWhenLinked(): void
    {
        $entity = new User();
        $entity->setUsername('profile_test');
        $entity->setEmail('profile_test@example.com');
        $entity->setPasswordHash('hash');
        $entity->setAuthKey('key');
        $entity->setCreatedAt(1000);
        $entity->setUpdatedAt(1000);
        $entity->save();

        $profile = new UserProfile();
        $profile->setUserId((int) $entity->getId());
        $profile->setBio('Test bio');
        $profile->save();

        $loaded = User::query()->where(['username' => 'profile_test'])->one();
        self::assertNotNull($loaded);

        $found = $loaded->getProfile();
        self::assertNotNull($found);
        self::assertSame('Test bio', $found->getBio());
    }

    public function testGetSetAuthKey(): void
    {
        $entity = new User();
        $entity->setAuthKey('auth_key_value');
        self::assertSame('auth_key_value', $entity->getAuthKey());
    }

    public function testGetSetAuthTfKey(): void
    {
        $entity = new User();
        $entity->setAuthTfKey('tfkey123');
        self::assertSame('tfkey123', $entity->getAuthTfKey());
    }

    public function testGetSetAuthTfKeyWithNull(): void
    {
        $entity = new User();
        $entity->setAuthTfKey(null);
        self::assertNull($entity->getAuthTfKey());
    }

    public function testGetSetAuthTfType(): void
    {
        $entity = new User();
        $entity->setAuthTfType('totp');
        self::assertSame('totp', $entity->getAuthTfType());
    }

    public function testGetSetAuthTfTypeWithNull(): void
    {
        $entity = new User();
        $entity->setAuthTfType(null);
        self::assertNull($entity->getAuthTfType());
    }

    public function testGetSetBlockedAt(): void
    {
        $entity = new User();
        $entity->setBlockedAt(12345);
        self::assertSame(12345, $entity->getBlockedAt());
    }

    public function testGetSetBlockedAtWithNull(): void
    {
        $entity = new User();
        $entity->setBlockedAt(null);
        self::assertNull($entity->getBlockedAt());
    }

    public function testGetSetConfirmedAt(): void
    {
        $entity = new User();
        $entity->setConfirmedAt(12345);
        self::assertSame(12345, $entity->getConfirmedAt());
    }

    public function testGetSetConfirmedAtWithNull(): void
    {
        $entity = new User();
        $entity->setConfirmedAt(null);
        self::assertNull($entity->getConfirmedAt());
    }

    public function testGetSetCreatedAt(): void
    {
        $entity = new User();
        $entity->setCreatedAt(1234567890);
        self::assertSame(1234567890, $entity->getCreatedAt());
    }

    public function testGetSetEmail(): void
    {
        $entity = new User();
        $entity->setEmail('user@example.com');
        self::assertSame('user@example.com', $entity->getEmail());
    }

    public function testGetSetFlags(): void
    {
        $entity = new User();
        $entity->setFlags(5);
        self::assertSame(5, $entity->getFlags());
    }

    public function testGetSetGdprConsentDate(): void
    {
        $entity = new User();
        $entity->setGdprConsentDate(12345);
        self::assertSame(12345, $entity->getGdprConsentDate());
    }

    public function testGetSetGdprConsentDateWithNull(): void
    {
        $entity = new User();
        $entity->setGdprConsentDate(null);
        self::assertNull($entity->getGdprConsentDate());
    }

    public function testGetSetLastLoginAt(): void
    {
        $entity = new User();
        $entity->setLastLoginAt(12345);
        self::assertSame(12345, $entity->getLastLoginAt());
    }

    public function testGetSetLastLoginAtWithNull(): void
    {
        $entity = new User();
        $entity->setLastLoginAt(null);
        self::assertNull($entity->getLastLoginAt());
    }

    public function testGetSetLastLoginIp(): void
    {
        $entity = new User();
        $entity->setLastLoginIp('10.0.0.1');
        self::assertSame('10.0.0.1', $entity->getLastLoginIp());
    }

    public function testGetSetLastLoginIpWithNull(): void
    {
        $entity = new User();
        $entity->setLastLoginIp(null);
        self::assertNull($entity->getLastLoginIp());
    }

    public function testGetSetPasswordChangedAt(): void
    {
        $entity = new User();
        $entity->setPasswordChangedAt(12345);
        self::assertSame(12345, $entity->getPasswordChangedAt());
    }

    public function testGetSetPasswordChangedAtWithNull(): void
    {
        $entity = new User();
        $entity->setPasswordChangedAt(null);
        self::assertNull($entity->getPasswordChangedAt());
    }

    public function testGetSetPasswordHash(): void
    {
        $entity = new User();
        $entity->setPasswordHash('hashed_password');
        self::assertSame('hashed_password', $entity->getPasswordHash());
    }

    public function testGetSetRegistrationIp(): void
    {
        $entity = new User();
        $entity->setRegistrationIp('192.168.1.1');
        self::assertSame('192.168.1.1', $entity->getRegistrationIp());
    }

    public function testGetSetRegistrationIpWithNull(): void
    {
        $entity = new User();
        $entity->setRegistrationIp(null);
        self::assertNull($entity->getRegistrationIp());
    }

    public function testGetSetUnconfirmedEmail(): void
    {
        $entity = new User();
        $entity->setUnconfirmedEmail('pending@example.com');
        self::assertSame('pending@example.com', $entity->getUnconfirmedEmail());
    }

    public function testGetSetUnconfirmedEmailWithNull(): void
    {
        $entity = new User();
        $entity->setUnconfirmedEmail(null);
        self::assertNull($entity->getUnconfirmedEmail());
    }

    public function testGetSetUpdatedAt(): void
    {
        $entity = new User();
        $entity->setUpdatedAt(1234567890);
        self::assertSame(1234567890, $entity->getUpdatedAt());
    }

    public function testGetSetUsername(): void
    {
        $entity = new User();
        $entity->setUsername('johndoe');
        self::assertSame('johndoe', $entity->getUsername());
    }

    public function testGetSocialNetworkAccountsReturnsQuery(): void
    {
        $entity = new User();
        $entity->setUsername('social_test');
        $entity->setEmail('social_test@example.com');
        $entity->setPasswordHash('hash');
        $entity->setAuthKey('key');
        $entity->setCreatedAt(1000);
        $entity->setUpdatedAt(1000);
        $entity->save();

        $userId = (int) $entity->getId();

        $account = new UserSocialAccount();
        $account->setUserId($userId);
        $account->setProvider('github');
        $account->setClientId('client123');
        $account->setCreatedAt(1000);
        $account->save();

        $loaded = User::query()->where(['username' => 'social_test'])->one();
        self::assertNotNull($loaded);

        $query = $loaded->getSocialNetworkAccounts();
        self::assertInstanceOf(\Yiisoft\ActiveRecord\ActiveQueryInterface::class, $query);

        $accounts = $query->all();
        self::assertCount(1, $accounts);
        self::assertInstanceOf(UserSocialAccount::class, $accounts[0]);
        self::assertSame('github', $accounts[0]->getProvider());
    }

    public function testGetTokensReturnsEmptyArrayWhenNone(): void
    {
        $entity = new User();
        $entity->setUsername('token_test_empty');
        $entity->setEmail('token_test_empty@example.com');
        $entity->setPasswordHash('hash');
        $entity->setAuthKey('key');
        $entity->setCreatedAt(1000);
        $entity->setUpdatedAt(1000);
        $entity->save();

        $loaded = User::query()->where(['username' => 'token_test_empty'])->one();
        self::assertNotNull($loaded);

        self::assertSame([], $loaded->getTokens());
    }

    public function testGetTokensReturnsTokensWhenExist(): void
    {
        $entity = new User();
        $entity->setUsername('token_test');
        $entity->setEmail('token_test@example.com');
        $entity->setPasswordHash('hash');
        $entity->setAuthKey('key');
        $entity->setCreatedAt(1000);
        $entity->setUpdatedAt(1000);
        $entity->save();

        $userId = (int) $entity->getId();

        $token = new UserToken();
        $token->setUserId($userId);
        $token->setCode('code123');
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(1000);
        $token->save();

        $loaded = User::query()->where(['username' => 'token_test'])->one();
        self::assertNotNull($loaded);

        $tokens = $loaded->getTokens();
        self::assertCount(1, $tokens);
        self::assertSame('code123', $tokens[0]->getCode());
    }

    public function testIsAdminByListReturnsFalse(): void
    {
        $entity = new User();
        $entity->setUsername('normal_user');
        self::assertFalse($entity->isAdminByList(['admin_user', 'other']));
    }

    public function testIsAdminByListReturnsTrue(): void
    {
        $entity = new User();
        $entity->setUsername('admin_user');
        self::assertTrue($entity->isAdminByList(['admin_user', 'other']));
    }

    public function testIsAdminByListWithEmptyList(): void
    {
        $entity = new User();
        $entity->setUsername('normal_user');
        self::assertFalse($entity->isAdminByList([]));
    }

    public function testIsAnonymizedWithFalse(): void
    {
        $entity = new User();
        $entity->setAnonymized(false);
        self::assertFalse($entity->isAnonymized());
    }

    public function testIsAnonymizedWithOne(): void
    {
        $entity = new User();
        $entity->setAnonymized(1);
        self::assertTrue($entity->isAnonymized());
    }

    public function testIsAnonymizedWithTrue(): void
    {
        $entity = new User();
        $entity->setAnonymized(true);
        self::assertTrue($entity->isAnonymized());
    }

    public function testIsAnonymizedWithZero(): void
    {
        $entity = new User();
        $entity->setAnonymized(0);
        self::assertFalse($entity->isAnonymized());
    }

    public function testIsAuthTfEnabledWithFalse(): void
    {
        $entity = new User();
        $entity->setAuthTfEnabled(false);
        self::assertFalse($entity->isAuthTfEnabled());
    }

    public function testIsAuthTfEnabledWithOne(): void
    {
        $entity = new User();
        $entity->setAuthTfEnabled(1);
        self::assertTrue($entity->isAuthTfEnabled());
    }

    public function testIsAuthTfEnabledWithTrue(): void
    {
        $entity = new User();
        $entity->setAuthTfEnabled(true);
        self::assertTrue($entity->isAuthTfEnabled());
    }

    public function testIsAuthTfEnabledWithZero(): void
    {
        $entity = new User();
        $entity->setAuthTfEnabled(0);
        self::assertFalse($entity->isAuthTfEnabled());
    }

    public function testIsBlockedReturnsFalse(): void
    {
        $entity = new User();
        $entity->setBlockedAt(null);
        self::assertFalse($entity->isBlocked());
    }

    public function testIsBlockedReturnsTrue(): void
    {
        $entity = new User();
        $entity->setBlockedAt(12345);
        self::assertTrue($entity->isBlocked());
    }

    public function testIsConfirmedReturnsFalse(): void
    {
        $entity = new User();
        $entity->setConfirmedAt(null);
        self::assertFalse($entity->isConfirmed());
    }

    public function testIsConfirmedReturnsTrue(): void
    {
        $entity = new User();
        $entity->setConfirmedAt(12345);
        self::assertTrue($entity->isConfirmed());
    }

    public function testIsGdprConsentWithFalse(): void
    {
        $entity = new User();
        $entity->setGdprConsent(false);
        self::assertFalse($entity->isGdprConsent());
    }

    public function testIsGdprConsentWithOne(): void
    {
        $entity = new User();
        $entity->setGdprConsent(1);
        self::assertTrue($entity->isGdprConsent());
    }

    public function testIsGdprConsentWithTrue(): void
    {
        $entity = new User();
        $entity->setGdprConsent(true);
        self::assertTrue($entity->isGdprConsent());
    }

    public function testIsGdprConsentWithZero(): void
    {
        $entity = new User();
        $entity->setGdprConsent(0);
        self::assertFalse($entity->isGdprConsent());
    }

    public function testNewEmailConfirmedConstant(): void
    {
        self::assertSame(2, User::NEW_EMAIL_CONFIRMED);
    }

    public function testOldEmailConfirmedConstant(): void
    {
        self::assertSame(1, User::OLD_EMAIL_CONFIRMED);
    }

    public function testTableName(): void
    {
        $entity = new User();
        self::assertSame('{{%user}}', $entity->tableName());
    }

    public function testValidateAuthKeyReturnsFalse(): void
    {
        $entity = new User();
        $entity->setAuthKey('valid_key');
        self::assertFalse($entity->validateAuthKey('wrong_key'));
    }

    public function testValidateAuthKeyReturnsTrue(): void
    {
        $entity = new User();
        $entity->setAuthKey('valid_key');
        self::assertTrue($entity->validateAuthKey('valid_key'));
    }

    public function testValidateAuthKeyWithEmptyString(): void
    {
        $entity = new User();
        $entity->setAuthKey('');
        self::assertTrue($entity->validateAuthKey(''));
        self::assertFalse($entity->validateAuthKey('non_empty'));
    }

    public function testValidateCookieLoginKeyReturnsFalse(): void
    {
        $entity = new User();
        $entity->setAuthKey('cookie_key_val');
        self::assertFalse($entity->validateCookieLoginKey('wrong'));
    }

    public function testValidateCookieLoginKeyReturnsTrue(): void
    {
        $entity = new User();
        $entity->setAuthKey('cookie_key_val');
        self::assertTrue($entity->validateCookieLoginKey('cookie_key_val'));
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
