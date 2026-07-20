<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserAuditLogTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        $connection = $this->createSqliteConnection();
        ConnectionProvider::set($connection);
        $this->connection = $connection;

        $this->connection->createCommand('
            CREATE TABLE "user_audit_log" (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT,
                "actor_user_id" INTEGER,
                "target_user_id" INTEGER,
                "target_name" VARCHAR(255),
                "action" VARCHAR(64) NOT NULL,
                "context" TEXT,
                "created_at" INTEGER NOT NULL
            )
        ')->execute();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_audit_log"')->execute();
        }
        ConnectionProvider::clear();
        $this->connection = null;
    }

    /**
     * @return iterable<string, array{string, string, int|string}>
     */
    public static function getterSetterProvider(): iterable
    {
        yield 'action' => ['setAction', 'getAction', 'user.create'];
        yield 'createdAt' => ['setCreatedAt', 'getCreatedAt', 5000];
    }

    public function testDefaultValues(): void
    {
        $entity = new UserAuditLog();
        self::assertSame('', $entity->getAction());
        self::assertNull($entity->getActorUserId());
        self::assertNull($entity->getContext());
        self::assertSame(0, $entity->getCreatedAt());
        self::assertNull($entity->getId());
        self::assertNull($entity->getTargetName());
        self::assertNull($entity->getTargetUserId());
    }

    public function testGetSetActorUserId(): void
    {
        $entity = new UserAuditLog();
        $entity->setActorUserId(5);
        self::assertSame(5, $entity->getActorUserId());
    }

    public function testGetSetContext(): void
    {
        $entity = new UserAuditLog();
        $entity->setContext('{"foo":"bar"}');
        self::assertSame('{"foo":"bar"}', $entity->getContext());
    }

    #[DataProvider('getterSetterProvider')]
    public function testGetSetProperty(string $setter, string $getter, int|string $value): void
    {
        $entity = new UserAuditLog();
        $entity->$setter($value);
        self::assertSame($value, $entity->$getter());
    }

    public function testGetSetTargetName(): void
    {
        $entity = new UserAuditLog();
        $entity->setTargetName('editor');
        self::assertSame('editor', $entity->getTargetName());
    }

    public function testGetSetTargetUserId(): void
    {
        $entity = new UserAuditLog();
        $entity->setTargetUserId(7);
        self::assertSame(7, $entity->getTargetUserId());
    }

    public function testPrimaryKey(): void
    {
        $entity = new UserAuditLog();
        self::assertSame(['id'], $entity->primaryKey());
    }

    public function testSearchFiltersByAction(): void
    {
        $this->createLog(1, 2, 'user.create');
        $this->createLog(1, 2, 'user.delete');

        $found = UserAuditLog::search(['action' => 'create'])->all();

        self::assertCount(1, $found);
        self::assertSame('user.create', $found[0]->getAction());
    }

    public function testSearchFiltersByActorUserId(): void
    {
        $this->createLog(1, 2, 'user.create');
        $this->createLog(3, 2, 'user.create');

        $found = UserAuditLog::search(['actor_user_id' => 1])->all();

        self::assertCount(1, $found);
        self::assertSame(1, $found[0]->getActorUserId());
    }

    public function testSearchFiltersByTargetUserId(): void
    {
        $this->createLog(1, 2, 'user.create');
        $this->createLog(1, 3, 'user.create');

        $found = UserAuditLog::search(['target_user_id' => 2])->all();

        self::assertCount(1, $found);
        self::assertSame(2, $found[0]->getTargetUserId());
    }

    public function testSearchWithoutFiltersOrdersByCreatedAtDescending(): void
    {
        $this->createLog(1, 2, 'user.create', 1000);
        $this->createLog(1, 2, 'user.delete', 2000);

        $found = UserAuditLog::search()->all();

        self::assertCount(2, $found);
        self::assertSame('user.delete', $found[0]->getAction());
        self::assertSame('user.create', $found[1]->getAction());
    }

    private function createLog(int $actorUserId, int $targetUserId, string $action, ?int $createdAt = null): void
    {
        $log = new UserAuditLog();
        $log->setActorUserId($actorUserId);
        $log->setTargetUserId($targetUserId);
        $log->setAction($action);
        $log->setCreatedAt($createdAt ?? time());
        $log->save();
    }

}
