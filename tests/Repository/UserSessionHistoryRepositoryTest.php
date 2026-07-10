<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Repository;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\UserSessionHistory;
use YiiRocks\Voyti\Repository\UserSessionHistoryRepository;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

final class UserSessionHistoryRepositoryTest extends TestCase
{
    use DatabaseSetupTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testFindAllSessionHistoryReturnsAll(): void
    {
        $repository = new UserSessionHistoryRepository();
        $this->createSession(1, 'sess-1', '203.0.113.1');
        $this->createSession(2, 'sess-2', '203.0.113.2');

        self::assertCount(2, $repository->findAllSessionHistory());
    }

    public function testFindByUserIdFiltersByUserId(): void
    {
        $repository = new UserSessionHistoryRepository();
        $this->createSession(1, 'sess-1', '203.0.113.1');
        $this->createSession(1, 'sess-1b', '203.0.113.3');
        $this->createSession(2, 'sess-2', '203.0.113.2');

        $sessions = $repository->findByUserId(1);

        self::assertCount(2, $sessions);
    }

    public function testSearchWithIpFilter(): void
    {
        $repository = new UserSessionHistoryRepository();
        $this->createSession(1, 'sess-1', '203.0.113.1');
        $this->createSession(1, 'sess-2', '198.51.100.1');

        $sessions = $repository->search(['ip' => '203.0.113']);

        self::assertCount(1, $sessions);
    }

    public function testSearchWithNoFiltersReturnsAll(): void
    {
        $repository = new UserSessionHistoryRepository();
        $this->createSession(1, 'sess-1', '203.0.113.1');
        $this->createSession(2, 'sess-2', '203.0.113.2');

        self::assertCount(2, $repository->search());
    }

    public function testSearchWithUserIdFilter(): void
    {
        $repository = new UserSessionHistoryRepository();
        $this->createSession(1, 'sess-1', '203.0.113.1');
        $this->createSession(2, 'sess-2', '203.0.113.2');

        $sessions = $repository->search(['user_id' => 1]);

        self::assertCount(1, $sessions);
        self::assertSame('sess-1', $sessions[0]->getSessionId());
    }

    private function createSession(int $userId, string $sessionId, string $ip): UserSessionHistory
    {
        $session = new UserSessionHistory();
        $session->setUserId($userId);
        $session->setSessionId($sessionId);
        $session->setIp($ip);
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();

        return $session;
    }
}
