<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserBackupCode;
use YiiRocks\Voyti\Model\UserPasswordHistory;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

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

        $this->connection->createCommand('
            CREATE TABLE "user_backup_code" (
                "user_id" INTEGER NOT NULL,
                "code_hash" VARCHAR(255) NOT NULL,
                "used_at" INTEGER,
                "created_at" INTEGER NOT NULL
            )
        ')->execute();

        $this->connection->createCommand('
            CREATE TABLE "user_password_history" (
                "user_id" INTEGER NOT NULL,
                "password_hash" VARCHAR(255) NOT NULL,
                "created_at" INTEGER NOT NULL
            )
        ')->execute();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_password_history"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_backup_code"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_sessions"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_token"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_social_account"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_profile"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user"')->execute();
        }
        ConnectionProvider::clear();
        $this->connection = null;
    }

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

    public function testDeleteCascadesRelatedRows(): void
    {
        $user = $this->createUser('alice', 'alice@example.com', createdAt: time());
        $userId = (int) $user->getId();

        $this->createRelatedRows($userId);

        $user->delete();

        self::assertCount(0, UserSocialAccount::findByUserId($userId));
        self::assertCount(0, UserToken::findByUserId($userId));
        self::assertCount(0, UserSessions::findByUserId($userId));
        self::assertCount(0, UserBackupCode::findUnusedByUserId($userId));
        self::assertCount(0, UserPasswordHistory::findByUserId($userId));
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

    public function testGetPasswordAgeJustUnderDay(): void
    {
        $entity = new User();
        $entity->setPasswordChangedAt(time() - 86399);
        $age = $entity->getPasswordAge();
        self::assertSame(0, $age);
    }

    public function testGetPasswordAgeWithNullPasswordChangedAt(): void
    {
        $entity = new User();
        $entity->setPasswordChangedAt(null);
        self::assertSame(9999, $entity->getPasswordAge());
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

    public function testIsGdprConsentWithOne(): void
    {
        $entity = new User();
        $entity->setGdprConsent(1);
        self::assertTrue($entity->isGdprConsent());
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

    public function testSearchQueryCountReflectsStatusFilter(): void
    {
        $blocked = $this->createUser('alice', 'alice@example.com', createdAt: time());
        $blocked->setBlockedAt(time());
        $blocked->save();
        $this->createUser('bob', 'bob@example.com', createdAt: time());

        self::assertSame(1, User::searchQuery(['status' => 'blocked'])->count());
        self::assertSame(2, User::searchQuery()->count());
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

    private function createRelatedRows(int $userId): void
    {
        $socialAccount = new UserSocialAccount();
        $socialAccount->setUserId($userId);
        $socialAccount->setProvider('github');
        $socialAccount->setClientId('client-' . $userId);
        $socialAccount->setCreatedAt(time());
        $socialAccount->save();

        $token = new UserToken();
        $token->setUserId($userId);
        $token->setCode('code-' . $userId);
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());
        $token->save();

        $session = new UserSessions();
        $session->setUserId($userId);
        $session->setSessionId('session-' . $userId);
        $session->setIp('203.0.113.1');
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();

        $backupCode = new UserBackupCode();
        $backupCode->setUserId($userId);
        $backupCode->setCodeHash('hash-' . $userId);
        $backupCode->setCreatedAt(time());
        $backupCode->save();

        $passwordHistory = new UserPasswordHistory();
        $passwordHistory->setUserId($userId);
        $passwordHistory->setPasswordHash('hash-' . $userId);
        $passwordHistory->setCreatedAt(time());
        $passwordHistory->save();
    }
}
