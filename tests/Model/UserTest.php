<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Dsn;

final class UserTest extends TestCase
{
    use UserFactoryTrait;

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
            CREATE TABLE "user_profile" (
                "user_id" INTEGER NOT NULL,
                "bio" TEXT,
                "birthday" DATE,
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
                "code" VARCHAR(64) NOT NULL,
                "type" SMALLINT NOT NULL,
                "created_at" INTEGER NOT NULL
            )
        ')->execute();

        $this->connection->createCommand('
            CREATE TABLE "user_sessions" (
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
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_sessions"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_token"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_social_account"')->execute();
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
        yield 'authKey' => ['setAuthKey', 'getAuthKey', 'auth_key_value'];
        yield 'authTfKey' => ['setAuthTfKey', 'getAuthTfKey', 'tfkey123'];
        yield 'authTfType' => ['setAuthTfType', 'getAuthTfType', 'totp'];
        yield 'blockedAt' => ['setBlockedAt', 'getBlockedAt', 12345];
        yield 'confirmedAt' => ['setConfirmedAt', 'getConfirmedAt', 12345];
        yield 'createdAt' => ['setCreatedAt', 'getCreatedAt', 1234567890];
        yield 'email' => ['setEmail', 'getEmail', 'user@example.com'];
        yield 'flags' => ['setFlags', 'getFlags', 5];
        yield 'gdprConsentDate' => ['setGdprConsentDate', 'getGdprConsentDate', 12345];
        yield 'lastLoginAt' => ['setLastLoginAt', 'getLastLoginAt', 12345];
        yield 'lastLoginIp' => ['setLastLoginIp', 'getLastLoginIp', '10.0.0.1'];
        yield 'passwordChangedAt' => ['setPasswordChangedAt', 'getPasswordChangedAt', 12345];
        yield 'passwordHash' => ['setPasswordHash', 'getPasswordHash', 'hashed_password'];
        yield 'registrationIp' => ['setRegistrationIp', 'getRegistrationIp', '192.168.1.1'];
        yield 'unconfirmedEmail' => ['setUnconfirmedEmail', 'getUnconfirmedEmail', 'pending@example.com'];
        yield 'updatedAt' => ['setUpdatedAt', 'getUpdatedAt', 1234567890];
        yield 'username' => ['setUsername', 'getUsername', 'johndoe'];
    }

    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function isAdminByListProvider(): iterable
    {
        yield 'returns false' => ['normal_user', ['admin_user', 'other'], false];
        yield 'returns true' => ['admin_user', ['admin_user', 'other'], true];
        yield 'with empty list' => ['normal_user', [], false];
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

    public function testDeleteRemovesUserAndProfile(): void
    {
        $user = $this->createUser('alice', 'alice@example.com', createdAt: time());

        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setName('Alice');
        $profile->save();

        $user->delete();

        self::assertNull(User::findByUsername('alice'));
        self::assertNull(UserProfile::findByUserId((int) $user->getId()));
    }

    public function testDeleteWithoutProfileOnlyRemovesUser(): void
    {
        $user = $this->createUser('alice', 'alice@example.com', createdAt: time());

        $user->delete();

        self::assertNull(User::findByUsername('alice'));
    }

    public function testFindAllUsersReturnsAllUsers(): void
    {
        $this->createUser('alice', 'alice@example.com', createdAt: time());
        $this->createUser('bob', 'bob@example.com', createdAt: time());

        self::assertCount(2, User::findAllUsers());
    }

    public function testFindByIdsReturnsMatchingUsers(): void
    {
        $alice = $this->createUser('alice', 'alice@example.com', createdAt: time());
        $bob = $this->createUser('bob', 'bob@example.com', createdAt: time());
        $this->createUser('carol', 'carol@example.com', createdAt: time());

        $result = User::findByIds([(int) $alice->getId(), (int) $bob->getId()]);

        self::assertCount(2, $result);
    }

    public function testFindByUsernameOrEmailMatchesByEmail(): void
    {
        $this->createUser('alice', 'alice@example.com', createdAt: time());

        $user = User::findByUsernameOrEmail('alice@example.com');

        self::assertNotNull($user);
        self::assertSame('alice', $user->getUsername());
    }

    public function testFindByUsernameOrEmailMatchesByUsername(): void
    {
        $this->createUser('alice', 'alice@example.com', createdAt: time());

        $user = User::findByUsernameOrEmail('alice');

        self::assertNotNull($user);
        self::assertSame('alice@example.com', $user->getEmail());
    }

    public function testGetCookieLoginKey(): void
    {
        $entity = new User();
        $entity->setAuthKey('cookie_key');
        self::assertSame('cookie_key', $entity->getCookieLoginKey());
    }

    public function testGetIdOrZeroReturnsIdWhenSet(): void
    {
        $entity = new User();
        $entity->setUsername('test');
        $entity->setEmail('test@example.com');
        $entity->setPasswordHash('hash');
        $entity->setAuthKey('key');
        $entity->setCreatedAt(1000);
        $entity->setUpdatedAt(1000);
        $entity->save();

        self::assertSame((int) $entity->getId(), $entity->getIdOrZero());
        self::assertGreaterThan(0, $entity->getIdOrZero());
    }

    public function testGetIdOrZeroReturnsZeroWhenNotSet(): void
    {
        $entity = new User();
        self::assertSame(0, $entity->getIdOrZero());
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

    #[DataProvider('getterSetterProvider')]
    public function testGetSetProperty(string $setter, string $getter, int|string $value): void
    {
        $entity = new User();
        $entity->$setter($value);
        self::assertSame($value, $entity->$getter());
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
        self::assertInstanceOf(ActiveQueryInterface::class, $query);

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

    /**
     * @param list<string> $adminList
     */
    #[DataProvider('isAdminByListProvider')]
    public function testIsAdminByList(string $username, array $adminList, bool $expected): void
    {
        $entity = new User();
        $entity->setUsername($username);
        self::assertSame($expected, $entity->isAdminByList($adminList));
    }

    public function testIsAnonymizedWithOne(): void
    {
        $entity = new User();
        $entity->setAnonymized(1);
        self::assertTrue($entity->isAnonymized());
    }

    public function testIsAnonymizedWithZero(): void
    {
        $entity = new User();
        $entity->setAnonymized(0);
        self::assertFalse($entity->isAnonymized());
    }

    public function testIsAuthTfEnabledWithOne(): void
    {
        $entity = new User();
        $entity->setAuthTfEnabled(1);
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

    public function testIsGdprConsentWithOne(): void
    {
        $entity = new User();
        $entity->setGdprConsent(1);
        self::assertTrue($entity->isGdprConsent());
    }

    public function testIsGdprConsentWithZero(): void
    {
        $entity = new User();
        $entity->setGdprConsent(0);
        self::assertFalse($entity->isGdprConsent());
    }

    public function testIsSwitchDisabledForReturnsFalseForOtherActiveUser(): void
    {
        $user = $this->createUser('alice', 'alice@example.com', createdAt: time());
        self::assertFalse($user->isSwitchDisabledFor((int) $user->getId() + 1));
    }

    public function testIsSwitchDisabledForReturnsTrueForBlockedUser(): void
    {
        $user = $this->createUser('alice', 'alice@example.com', createdAt: time());
        $user->setBlockedAt(time());
        $user->save();
        self::assertTrue($user->isSwitchDisabledFor((int) $user->getId() + 1));
    }

    public function testIsSwitchDisabledForReturnsTrueForSameUser(): void
    {
        $user = $this->createUser('alice', 'alice@example.com', createdAt: time());
        self::assertTrue($user->isSwitchDisabledFor((int) $user->getId()));
    }

    public function testNewEmailConfirmedConstant(): void
    {
        self::assertSame(2, User::NEW_EMAIL_CONFIRMED);
    }

    public function testOldEmailConfirmedConstant(): void
    {
        self::assertSame(1, User::OLD_EMAIL_CONFIRMED);
    }

    public function testSearchQueryCountReflectsStatusFilter(): void
    {
        $blocked = $this->createUser('alice', 'alice@example.com', createdAt: time());
        $blocked->setBlockedAt(time());
        $blocked->save();
        $this->createUser('bob', 'bob@example.com', createdAt: time());

        self::assertSame(1, User::searchQuery(['status' => 'blocked'])->count());
        self::assertSame(2, User::searchQuery()->count());
    }

    public function testSearchQueryWithBlockedStatusFilter(): void
    {
        $blocked = $this->createUser('alice', 'alice@example.com', createdAt: time());
        $blocked->setBlockedAt(time());
        $blocked->save();
        $this->createUser('bob', 'bob@example.com', createdAt: time());

        $result = User::searchQuery(['status' => 'blocked'])->all();

        self::assertCount(1, $result);
        self::assertSame('alice', $result[0]->getUsername());
    }

    public function testSearchQueryWithConfirmedStatusFilter(): void
    {
        $confirmed = $this->createUser('alice', 'alice@example.com', createdAt: time());
        $confirmed->setConfirmedAt(time());
        $confirmed->save();
        $this->createUser('bob', 'bob@example.com', createdAt: time());

        $result = User::searchQuery(['status' => 'confirmed'])->all();

        self::assertCount(1, $result);
        self::assertSame('alice', $result[0]->getUsername());
    }

    public function testSearchQueryWithEmailFilter(): void
    {
        $this->createUser('alice', 'alice@example.com', createdAt: time());
        $this->createUser('bob', 'bob@other.com', createdAt: time());

        $result = User::searchQuery(['email' => 'example.com'])->all();

        self::assertCount(1, $result);
        self::assertSame('alice', $result[0]->getUsername());
    }

    public function testSearchQueryWithUnconfirmedStatusFilter(): void
    {
        $confirmed = $this->createUser('alice', 'alice@example.com', createdAt: time());
        $confirmed->setConfirmedAt(time());
        $confirmed->save();
        $this->createUser('bob', 'bob@example.com', createdAt: time());

        $result = User::searchQuery(['status' => 'unconfirmed'])->all();

        self::assertCount(1, $result);
        self::assertSame('bob', $result[0]->getUsername());
    }

    public function testSearchQueryWithUsernameFilter(): void
    {
        $this->createUser('alice', 'alice@example.com', createdAt: time());
        $this->createUser('bob', 'bob@example.com', createdAt: time());

        $result = User::searchQuery(['username' => 'ali'])->all();

        self::assertCount(1, $result);
        self::assertSame('alice', $result[0]->getUsername());
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
