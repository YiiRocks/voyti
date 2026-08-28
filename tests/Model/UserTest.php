<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserPasswordHistory;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\TestCase;
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
                "blocked_at" INTEGER,
                "confirmed_at" INTEGER,
                "created_at" INTEGER NOT NULL,
                "flags" INTEGER NOT NULL DEFAULT 0,
                "data_processing_consent_date" INTEGER,
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
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_sessions"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_token"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_profile"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user"')->execute();
        }
        ConnectionProvider::clear();
        $this->connection = null;
    }

    public static function booleanFlagProvider(): iterable
    {
        yield 'anonymized' => ['setAnonymized', 'isAnonymized'];
    }

    public static function findByUsernameOrEmailProvider(): iterable
    {
        yield 'by email' => ['alice@example.com', 'alice', 'alice@example.com'];
        yield 'by username' => ['alice', 'alice', 'alice@example.com'];
    }

    public static function getPasswordAgeProvider(): iterable
    {
        yield 'just under day' => [-86399, 0];
        yield 'null password changed at' => [null, 9999];
    }

    public static function getterSetterProvider(): iterable
    {
        yield 'authKey' => ['setAuthKey', 'getAuthKey', 'auth_key_value'];
        yield 'blockedAt' => ['setBlockedAt', 'getBlockedAt', 12345];
        yield 'confirmedAt' => ['setConfirmedAt', 'getConfirmedAt', 12345];
        yield 'createdAt' => ['setCreatedAt', 'getCreatedAt', 1234567890];
        yield 'email' => ['setEmail', 'getEmail', 'user@example.com'];
        yield 'flags' => ['setFlags', 'getFlags', 5];
        yield 'dataProcessingConsentDate' => ['setDataProcessingConsentDate', 'getDataProcessingConsentDate', 12345];
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

    public static function isSwitchDisabledForProvider(): iterable
    {
        yield 'other active user' => [false, false, false];
        yield 'blocked user' => [true, false, true];
        yield 'same user' => [false, true, true];
    }

    public static function searchQueryStatusProvider(): iterable
    {
        yield 'blocked' => [true, false, 'blocked', ['alice']];
        yield 'confirmed' => [false, true, 'confirmed', ['alice']];
        yield 'unconfirmed' => [false, true, 'unconfirmed', ['bob']];
    }

    public static function searchQueryTextFilterProvider(): iterable
    {
        yield 'by email' => [['email' => 'example.com'], 'bob@other.com', ['alice']];
        yield 'by username' => [['username' => 'ali'], 'bob@example.com', ['alice']];
    }

    #[DataProvider('booleanFlagProvider')]
    public function testBooleanFlags(string $setter, string $getter): void
    {
        $entity = new User();
        $entity->$setter(1);
        self::assertTrue($entity->$getter());
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
        self::assertFalse($entity->hasDataProcessingConsent());
        self::assertFalse($entity->isAnonymized());
        self::assertNull($entity->getDataProcessingConsentDate());
    }

    public function testDelete(): void
    {
        // Cascades to related rows
        $user = $this->createUser('alice', 'alice@example.com', createdAt: time());
        $userId = (int) $user->getId();

        $this->createRelatedRows($userId);

        $user->delete();

        self::assertCount(0, UserToken::findByUserId($userId));
        self::assertCount(0, UserSessions::findByUserId($userId));
        self::assertCount(0, UserPasswordHistory::findByUserId($userId));

        // Removes user and profile
        $user = $this->createUser('carol', 'carol@example.com', createdAt: time());

        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setName('Carol');
        $profile->save();

        $user->delete();

        self::assertNull(User::findByUsername('carol'));
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

    #[DataProvider('findByUsernameOrEmailProvider')]
    public function testFindByUsernameOrEmail(string $lookupValue, string $expectedUsername, string $expectedEmail): void
    {
        $this->createUser('alice', 'alice@example.com', createdAt: time());

        $user = User::findByUsernameOrEmail($lookupValue);

        self::assertNotNull($user);
        self::assertSame($expectedUsername, $user->getUsername());
        self::assertSame($expectedEmail, $user->getEmail());
    }

    #[DataProvider('getPasswordAgeProvider')]
    public function testGetPasswordAge(?int $offset, int $expectedAge): void
    {
        $entity = new User();
        $passwordChangedAt = $offset === null ? null : time() + $offset;
        $entity->setPasswordChangedAt($passwordChangedAt);
        self::assertSame($expectedAge, $entity->getPasswordAge());
    }

    #[DataProvider('getterSetterProvider')]
    public function testGetSetProperty(string $setter, string $getter, int|string $value): void
    {
        $entity = new User();
        $entity->$setter($value);
        self::assertSame($value, $entity->$getter());
    }

    public function testGetTokens(): void
    {
        // Returns empty array when none exist
        $empty = $this->createUser('token_empty', 'token_empty@example.com', createdAt: time());
        $loadedEmpty = User::query()->where(['username' => 'token_empty'])->one();
        self::assertNotNull($loadedEmpty);
        self::assertSame([], $loadedEmpty->getTokens());

        // Returns tokens when present
        $entity = $this->createUser('token_user', 'token_user@example.com', createdAt: time());
        $userId = (int) $entity->getId();

        $token = new UserToken();
        $token->setUserId($userId);
        $token->setCode('code123');
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(1000);
        $token->save();

        $loaded = User::query()->where(['username' => 'token_user'])->one();
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

    #[DataProvider('isSwitchDisabledForProvider')]
    public function testIsSwitchDisabledFor(bool $shouldBlock, bool $targetIsSelf, bool $expected): void
    {
        $user = $this->createUser('alice', 'alice@example.com', createdAt: time());

        if ($shouldBlock) {
            $user->setBlockedAt(time());
            $user->save();
        }

        $targetId = $targetIsSelf ? (int) $user->getId() : (int) $user->getId() + 1;

        self::assertSame($expected, $user->isSwitchDisabledFor($targetId));
    }

    #[DataProvider('searchQueryStatusProvider')]
    public function testSearchQueryStatusFilter(bool $aliceBlocked, bool $aliceConfirmed, string $status, array $expectedUsernames): void
    {
        $alice = $this->createUser(
            'alice',
            'alice@example.com',
            createdAt: time(),
            confirmedAt: $aliceConfirmed ? time() : null,
            blockedAt: $aliceBlocked ? time() : null,
        );
        self::assertNotNull($alice);
        $this->createUser('bob', 'bob@example.com', createdAt: time());

        $result = User::searchQuery(['status' => $status])->all();

        self::assertSame(2, User::searchQuery()->count());
        self::assertSame(
            $expectedUsernames,
            array_map(static fn(User $user): string => $user->getUsername(), $result),
        );
    }

    #[DataProvider('searchQueryTextFilterProvider')]
    public function testSearchQueryTextFilter(array $filter, string $bobEmail, array $expectedUsernames): void
    {
        $this->createUser('alice', 'alice@example.com', createdAt: time());
        $this->createUser('bob', $bobEmail, createdAt: time());

        $result = User::searchQuery($filter)->all();

        self::assertSame(
            $expectedUsernames,
            array_map(static fn(User $user): string => $user->getUsername(), $result),
        );
    }

    public function testValidateAuthKeyReturnsFalse(): void
    {
        $entity = new User();
        $entity->setAuthKey('valid_key');
        self::assertFalse($entity->validateAuthKey('wrong_key'));
    }

    private function createRelatedRows(int $userId): void
    {
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

        $passwordHistory = new UserPasswordHistory();
        $passwordHistory->setUserId($userId);
        $passwordHistory->setPasswordHash('hash-' . $userId);
        $passwordHistory->setCreatedAt(time());
        $passwordHistory->save();
    }
}
