<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model;

use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\tests\Support\UserSessionFactoryTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserSessionsTest extends TestCase
{
    use UserSessionFactoryTrait;

    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        $connection = $this->createSqliteConnection();
        ConnectionProvider::set($connection);
        $this->connection = $connection;

        $this->connection->createCommand('
            CREATE TABLE IF NOT EXISTS "user_sessions" (
                "user_id" INTEGER NOT NULL,
                "session_id" VARCHAR(64) NOT NULL,
                "ip" VARCHAR(45),
                "user_agent" TEXT,
                "created_at" INTEGER NOT NULL,
                "updated_at" INTEGER NOT NULL,
                "revoked_at" INTEGER,
                PRIMARY KEY ("user_id", "session_id")
            )
        ')->execute();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
        }
    }

    public static function getterSetterProvider(): iterable
    {
        yield 'createdAt' => ['setCreatedAt', 'getCreatedAt', 1000];
        yield 'ip' => ['setIp', 'getIp', '192.168.1.1'];
        yield 'revokedAt' => ['setRevokedAt', 'getRevokedAt', 3000];
        yield 'sessionId' => ['setSessionId', 'getSessionId', 'sess-abc-123'];
        yield 'updatedAt' => ['setUpdatedAt', 'getUpdatedAt', 2000];
        yield 'userAgent' => ['setUserAgent', 'getUserAgent', 'Mozilla/5.0'];
        yield 'userId' => ['setUserId', 'getUserId', 42];
    }

    public function testClaimByUserIdAndSessionIdRevokesOnlyThatUsersSession(): void
    {
        // Two users share the same session id; the claim must revoke only the target user's row.
        $this->createUserSession(1, 'shared-sess', '203.0.113.1');
        $this->createUserSession(2, 'shared-sess', '203.0.113.2');

        self::assertTrue(UserSessions::claimByUserIdAndSessionId(1, 'shared-sess'));

        self::assertTrue(UserSessions::findByUserIdAndSessionId(1, 'shared-sess')?->isRevoked());
        self::assertFalse(UserSessions::findByUserIdAndSessionId(2, 'shared-sess')?->isRevoked());
    }

    public function testDefaultValues(): void
    {
        $entity = new UserSessions();
        self::assertSame(0, $entity->getUserId());
        self::assertSame('', $entity->getSessionId());
        self::assertSame(0, $entity->getCreatedAt());
        self::assertSame(0, $entity->getUpdatedAt());
        self::assertNull($entity->getIp());
        self::assertNull($entity->getUserAgent());
        self::assertNull($entity->getRevokedAt());
        self::assertFalse($entity->isRevoked());
    }

    public function testDeleteAllByUserIdRemovesOnlyThatUsersSessions(): void
    {
        $this->createUserSession(1, 'sess-1', '203.0.113.1');
        $this->createUserSession(2, 'sess-2', '203.0.113.2');

        UserSessions::deleteAllByUserId(1);

        self::assertCount(0, UserSessions::findByUserId(1));
        self::assertCount(1, UserSessions::findByUserId(2));
    }

    public function testFindAllSessionsReturnsAll(): void
    {
        $this->createUserSession(1, 'sess-1', '203.0.113.1');
        $this->createUserSession(2, 'sess-2', '203.0.113.2');

        self::assertCount(2, UserSessions::findAllSessions());
    }

    public function testFindByUserIdFiltersByUserId(): void
    {
        $this->createUserSession(1, 'sess-1', '203.0.113.1');
        $this->createUserSession(1, 'sess-1b', '203.0.113.3');
        $this->createUserSession(2, 'sess-2', '203.0.113.2');

        $sessions = UserSessions::findByUserId(1);

        self::assertCount(2, $sessions);
    }

    public function testPrimaryKey(): void
    {
        $entity = new UserSessions();
        self::assertSame(['user_id', 'session_id'], $entity->primaryKey());
    }

    public function testSearch(): void
    {
        // Filters by IP
        $this->createUserSession(1, 'sess-1', '203.0.113.1');
        $this->createUserSession(1, 'sess-2', '198.51.100.1');
        self::assertCount(1, UserSessions::search(['ip' => '203.0.113']));

        // Combines user id and IP filters
        $this->createUserSession(2, 'sess-3', '203.0.113.1');
        $sessions = UserSessions::search(['user_id' => 1, 'ip' => '203.0.113']);
        self::assertCount(1, $sessions);
        self::assertSame('sess-1', $sessions[0]->getSessionId());
    }
}
