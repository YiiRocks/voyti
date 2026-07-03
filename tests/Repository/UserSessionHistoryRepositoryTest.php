<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Repository;

use YiiRocks\Voyti\Entity\UserSessionHistory;
use YiiRocks\Voyti\Repository\UserSessionHistoryRepository;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserSessionHistoryRepositoryTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConnectionProvider::set($this->getDb());
        $this->getDb()->createCommand('CREATE TABLE {{%user_session_history}} (
            session_id VARCHAR(64) NOT NULL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            ip VARCHAR(45),
            user_agent VARCHAR(255),
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL
        )')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_session_history}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testFindByUserIdOnlyReturnsSessionsForGivenUser(): void
    {
        $this->insertSession('session-1', 1, '203.0.113.1');
        $this->insertSession('session-2', 2, '203.0.113.2');
        $this->insertSession('session-3', 1, '203.0.113.3');

        $repository = new UserSessionHistoryRepository();
        $result = $repository->findByUserId(1);

        $sessionIds = array_map(static fn (UserSessionHistory $session): string => $session->getSessionId(), $result);
        sort($sessionIds);
        self::assertSame(['session-1', 'session-3'], $sessionIds);
        foreach ($result as $session) {
            self::assertSame(1, $session->getUserId());
        }
    }

    public function testFindByUserIdReturnsEmptyArrayWhenNoMatchingUser(): void
    {
        $this->insertSession('session-1', 1, '203.0.113.1');

        $repository = new UserSessionHistoryRepository();
        $result = $repository->findByUserId(999);

        self::assertSame([], $result);
    }

    private function insertSession(string $sessionId, int $userId, string $ip): void
    {
        $session = new UserSessionHistory();
        $session->setSessionId($sessionId);
        $session->setUserId($userId);
        $session->setIp($ip);
        $session->setCreatedAt(1_700_000_000);
        $session->setUpdatedAt(1_700_000_000);
        $session->save();
    }
}
