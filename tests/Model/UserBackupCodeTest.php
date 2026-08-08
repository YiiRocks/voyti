<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model;

use YiiRocks\Voyti\Model\UserBackupCode;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserBackupCodeTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        $connection = $this->createSqliteConnection();
        ConnectionProvider::set($connection);
        $this->connection = $connection;

        $this->connection->createCommand('
            CREATE TABLE "user_backup_code" (
                "user_id" INTEGER NOT NULL,
                "code_hash" VARCHAR(255) NOT NULL,
                "used_at" INTEGER,
                "created_at" INTEGER NOT NULL,
                PRIMARY KEY ("user_id", "code_hash")
            )
        ')->execute();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_backup_code"')->execute();
        }
        ConnectionProvider::clear();
        $this->connection = null;
    }

    public static function getterSetterProvider(): iterable
    {
        yield 'codeHash' => ['setCodeHash', 'getCodeHash', 'abc123'];
        yield 'createdAt' => ['setCreatedAt', 'getCreatedAt', 5000];
        yield 'userId' => ['setUserId', 'getUserId', 42];
    }

    public function testDefaultValues(): void
    {
        $entity = new UserBackupCode();
        self::assertSame(0, $entity->getUserId());
        self::assertSame('', $entity->getCodeHash());
        self::assertNull($entity->getUsedAt());
        self::assertSame(0, $entity->getCreatedAt());
    }

    public function testDeleteAllByUserIdRemovesOnlyThatUsersCodes(): void
    {
        $code1 = new UserBackupCode();
        $code1->setUserId(1);
        $code1->setCodeHash('hash1');
        $code1->setCreatedAt(time());
        $code1->save();

        $code2 = new UserBackupCode();
        $code2->setUserId(2);
        $code2->setCodeHash('hash2');
        $code2->setCreatedAt(time());
        $code2->save();

        UserBackupCode::deleteAllByUserId(1);

        self::assertCount(0, UserBackupCode::findUnusedByUserId(1));
        self::assertCount(1, UserBackupCode::findUnusedByUserId(2));
    }

    public function testFindUnusedByUserIdExcludesUsedCodes(): void
    {
        $unused = new UserBackupCode();
        $unused->setUserId(1);
        $unused->setCodeHash('unused-hash');
        $unused->setCreatedAt(time());
        $unused->save();

        $used = new UserBackupCode();
        $used->setUserId(1);
        $used->setCodeHash('used-hash');
        $used->setCreatedAt(time());
        $used->setUsedAt(time());
        $used->save();

        $found = UserBackupCode::findUnusedByUserId(1);

        self::assertCount(1, $found);
        self::assertSame('unused-hash', $found[0]->getCodeHash());
    }

    public function testMarkUsedFailsWhenAlreadyUsedConcurrently(): void
    {
        $code = new UserBackupCode();
        $code->setUserId(1);
        $code->setCodeHash('race-hash');
        $code->setCreatedAt(time());
        $code->save();

        $first = UserBackupCode::findUnusedByUserId(1)[0];
        $second = UserBackupCode::findUnusedByUserId(1)[0];

        self::assertTrue($first->markUsed());
        self::assertFalse($second->markUsed());
    }

    public function testMarkUsedScopesToOwningUser(): void
    {
        $ownCode = new UserBackupCode();
        $ownCode->setUserId(1);
        $ownCode->setCodeHash('shared-hash');
        $ownCode->setCreatedAt(time());
        $ownCode->save();

        $otherUsersCode = new UserBackupCode();
        $otherUsersCode->setUserId(2);
        $otherUsersCode->setCodeHash('shared-hash');
        $otherUsersCode->setCreatedAt(time());
        $otherUsersCode->save();

        self::assertTrue($ownCode->markUsed());

        $stillUnused = UserBackupCode::query()->where(['user_id' => 2, 'code_hash' => 'shared-hash'])->one();
        self::assertNotNull($stillUnused);
        self::assertNull($stillUnused->getUsedAt());
    }

    public function testPrimaryKey(): void
    {
        $entity = new UserBackupCode();
        self::assertSame(['user_id', 'code_hash'], $entity->primaryKey());
    }
}
