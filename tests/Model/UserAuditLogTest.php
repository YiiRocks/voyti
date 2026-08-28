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
                "actor_ip" VARCHAR(45) NOT NULL,
                "actor_user_agent" TEXT,
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

    public static function getterSetterProvider(): iterable
    {
        yield 'action' => ['setAction', 'getAction', 'user.create'];
        yield 'actorIp' => ['setActorIp', 'getActorIp', '203.0.113.5'];
        yield 'actorUserAgent' => ['setActorUserAgent', 'getActorUserAgent', 'curl/8.0'];
        yield 'actorUserId' => ['setActorUserId', 'getActorUserId', 5];
        yield 'context' => ['setContext', 'getContext', '{"foo":"bar"}'];
        yield 'createdAt' => ['setCreatedAt', 'getCreatedAt', 5000];
        yield 'targetName' => ['setTargetName', 'getTargetName', 'editor'];
        yield 'targetUserId' => ['setTargetUserId', 'getTargetUserId', 7];
    }

    public static function searchFilterProvider(): iterable
    {
        yield 'by action' => [
            [[1, 2, 'user.create'], [1, 2, 'user.delete']],
            ['action' => 'create'],
        ];
        yield 'by actor user id' => [
            [[1, 2, 'user.create'], [3, 2, 'user.create']],
            ['actor_user_id' => 1],
        ];
        yield 'by target user id' => [
            [[1, 2, 'user.create'], [1, 3, 'user.create']],
            ['target_user_id' => 2],
        ];
    }

    public function testDefaultValues(): void
    {
        $entity = new UserAuditLog();
        self::assertSame('', $entity->getAction());
        self::assertSame('', $entity->getActorIp());
        self::assertNull($entity->getActorUserAgent());
        self::assertNull($entity->getActorUserId());
        self::assertNull($entity->getContext());
        self::assertSame(0, $entity->getCreatedAt());
        self::assertNull($entity->getId());
        self::assertNull($entity->getTargetName());
        self::assertNull($entity->getTargetUserId());
    }

    #[DataProvider('getterSetterProvider')]
    public function testGetSetProperty(string $setter, string $getter, int|string $value): void
    {
        $entity = new UserAuditLog();
        $entity->$setter($value);
        self::assertSame($value, $entity->$getter());
    }

    public function testPrimaryKey(): void
    {
        $entity = new UserAuditLog();
        self::assertSame(['id'], $entity->primaryKey());
    }

    #[DataProvider('searchFilterProvider')]
    public function testSearchFilters(array $rows, array $filter): void
    {
        foreach ($rows as [$actorUserId, $targetUserId, $action]) {
            $this->createLog($actorUserId, $targetUserId, $action);
        }

        $found = UserAuditLog::search($filter)->all();

        self::assertCount(1, $found);
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
