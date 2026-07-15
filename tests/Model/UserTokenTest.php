<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
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

    /**
     * @return iterable<string, array{int, int, int|null, bool}>
     */
    public static function getIsExpiredProvider(): iterable
    {
        yield 'default lifespan for confirmation boundary' => [86401, UserToken::TYPE_CONFIRMATION, null, true];
        yield 'default lifespan for confirmation edge case' => [86400, UserToken::TYPE_CONFIRMATION, null, false];
        yield 'default lifespan for confirmation expired' => [100000, UserToken::TYPE_CONFIRMATION, null, true];
        yield 'default lifespan for confirmation not expired' => [0, UserToken::TYPE_CONFIRMATION, null, false];
        yield 'default lifespan for confirm new email' => [100000, UserToken::TYPE_CONFIRM_NEW_EMAIL, null, true];
        yield 'default lifespan for confirm old email' => [100000, UserToken::TYPE_CONFIRM_OLD_EMAIL, null, true];
        yield 'default lifespan for recovery boundary' => [21601, UserToken::TYPE_RECOVERY, null, true];
        yield 'default lifespan for recovery edge case' => [21600, UserToken::TYPE_RECOVERY, null, false];
        yield 'default lifespan for recovery expired' => [50000, UserToken::TYPE_RECOVERY, null, true];
        yield 'default lifespan for recovery not expired' => [0, UserToken::TYPE_RECOVERY, null, false];
        yield 'custom lifespan edge case' => [86400, UserToken::TYPE_CONFIRMATION, 86400, false];
        yield 'custom lifespan expired' => [100, UserToken::TYPE_CONFIRMATION, 50, true];
        yield 'custom lifespan not expired' => [0, UserToken::TYPE_CONFIRMATION, 86400, false];
    }

    /**
     * @return iterable<string, array{string, string, int|string}>
     */
    public static function getterSetterProvider(): iterable
    {
        yield 'code' => ['setCode', 'getCode', 'abc123'];
        yield 'createdAt' => ['setCreatedAt', 'getCreatedAt', 5000];
        yield 'type' => ['setType', 'getType', UserToken::TYPE_CONFIRMATION];
        yield 'userId' => ['setUserId', 'getUserId', 42];
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

    public function testDeleteAllByUserIdAndTypeRemovesOnlyMatchingTypeForThatUser(): void
    {
        $confirmationToken = new UserToken();
        $confirmationToken->setUserId(1);
        $confirmationToken->setCode('confirm');
        $confirmationToken->setType(UserToken::TYPE_CONFIRMATION);
        $confirmationToken->setCreatedAt(time());
        $confirmationToken->save();

        $recoveryToken = new UserToken();
        $recoveryToken->setUserId(1);
        $recoveryToken->setCode('recovery');
        $recoveryToken->setType(UserToken::TYPE_RECOVERY);
        $recoveryToken->setCreatedAt(time());
        $recoveryToken->save();

        $otherUserConfirmationToken = new UserToken();
        $otherUserConfirmationToken->setUserId(2);
        $otherUserConfirmationToken->setCode('confirm2');
        $otherUserConfirmationToken->setType(UserToken::TYPE_CONFIRMATION);
        $otherUserConfirmationToken->setCreatedAt(time());
        $otherUserConfirmationToken->save();

        UserToken::deleteAllByUserIdAndType(1, UserToken::TYPE_CONFIRMATION);

        $remaining = UserToken::findByUserId(1);
        self::assertCount(1, $remaining);
        self::assertSame(UserToken::TYPE_RECOVERY, $remaining[0]->getType());
        self::assertCount(1, UserToken::findByUserId(2));
    }

    public function testDeleteAllByUserIdRemovesOnlyThatUsersTokens(): void
    {
        $token1 = new UserToken();
        $token1->setUserId(1);
        $token1->setCode('user1token');
        $token1->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token1->setCreatedAt(time());
        $token1->save();

        $token2 = new UserToken();
        $token2->setUserId(2);
        $token2->setCode('user2token');
        $token2->setType(UserToken::TYPE_RECOVERY);
        $token2->setCreatedAt(time());
        $token2->save();

        UserToken::deleteAllByUserId(1);

        self::assertCount(0, UserToken::findByUserId(1));
        self::assertCount(1, UserToken::findByUserId(2));
    }

    public function testFindByCodeAndTypeFiltersByCode(): void
    {
        $token1 = new UserToken();
        $token1->setUserId(1);
        $token1->setCode('codeB');
        $token1->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token1->setCreatedAt(time());
        $token1->save();

        $token2 = new UserToken();
        $token2->setUserId(1);
        $token2->setCode('codeA');
        $token2->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token2->setCreatedAt(time());
        $token2->save();

        $found = UserToken::findByCodeAndType('codeA', UserToken::TYPE_CONFIRM_NEW_EMAIL);
        self::assertNotNull($found);
        self::assertSame('codeA', $found->getCode());
    }

    public function testFindByUserIdAndCodeAndTypeReturnsMatch(): void
    {
        $token = new UserToken();
        $token->setUserId(1);
        $token->setCode('codeA');
        $token->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token->setCreatedAt(time());
        $token->save();

        $found = UserToken::findByUserIdAndCodeAndType(1, 'codeA', UserToken::TYPE_CONFIRM_NEW_EMAIL);
        self::assertNotNull($found);
        self::assertSame('codeA', $found->getCode());

        self::assertNull(UserToken::findByUserIdAndCodeAndType(1, 'codeA', UserToken::TYPE_RECOVERY));
    }

    public function testFindByUserIdAndCodeReturnsMatch(): void
    {
        $token = new UserToken();
        $token->setUserId(1);
        $token->setCode('codeA');
        $token->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token->setCreatedAt(time());
        $token->save();

        $found = UserToken::findByUserIdAndCode(1, 'codeA');
        self::assertNotNull($found);
        self::assertSame('codeA', $found->getCode());

        self::assertNull(UserToken::findByUserIdAndCode(2, 'codeA'));
    }

    public function testFindByUserIdFiltersByUserId(): void
    {
        $token1 = new UserToken();
        $token1->setUserId(1);
        $token1->setCode('user1token');
        $token1->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token1->setCreatedAt(time());
        $token1->save();

        $token2 = new UserToken();
        $token2->setUserId(2);
        $token2->setCode('user2token');
        $token2->setType(UserToken::TYPE_RECOVERY);
        $token2->setCreatedAt(time());
        $token2->save();

        $tokens = UserToken::findByUserId(1);
        self::assertCount(1, $tokens);
        self::assertSame('user1token', $tokens[0]->getCode());
    }

    public function testFindByUserIdRespectsAllResultsWhenMultipleMatch(): void
    {
        $token1 = new UserToken();
        $token1->setUserId(1);
        $token1->setCode('tokenA');
        $token1->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token1->setCreatedAt(time());
        $token1->save();

        $token2 = new UserToken();
        $token2->setUserId(1);
        $token2->setCode('tokenB');
        $token2->setType(UserToken::TYPE_RECOVERY);
        $token2->setCreatedAt(time());
        $token2->save();

        $tokens = UserToken::findByUserId(1);
        self::assertCount(2, $tokens);
    }

    public function testFindByUserIdTypeAndCodeReturnsMatch(): void
    {
        $token = new UserToken();
        $token->setUserId(1);
        $token->setCode('codeA');
        $token->setType(UserToken::TYPE_CONFIRM_NEW_EMAIL);
        $token->setCreatedAt(time());
        $token->save();

        $found = UserToken::findByUserIdAndCodeAndType(1, 'codeA', UserToken::TYPE_CONFIRM_NEW_EMAIL);
        self::assertNotNull($found);
        self::assertSame('codeA', $found->getCode());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getIsExpiredProvider')]
    public function testGetIsExpired(int $offset, int $type, ?int $lifespan, bool $expected): void
    {
        $entity = new UserToken();
        $entity->setCreatedAt(time() - $offset);
        $entity->setType($type);

        self::assertSame($expected, $lifespan === null ? $entity->getIsExpired() : $entity->getIsExpired($lifespan));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getterSetterProvider')]
    public function testGetSetProperty(string $setter, string $getter, int|string $value): void
    {
        $entity = new UserToken();
        $entity->$setter($value);
        self::assertSame($value, $entity->$getter());
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
