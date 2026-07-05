<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\UserSessionHistory;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\UserSessionHistory;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Service\UserSessionHistory\TerminateUserSessionsService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class TerminateUserSessionsServiceTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConnectionProvider::set($this->getDb());
        $db = $this->getDb();
        $db->createCommand('CREATE TABLE {{%user_session_history}} (
            user_id INTEGER NOT NULL,
            session_id VARCHAR(255) NOT NULL,
            user_agent TEXT,
            ip VARCHAR(45),
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            PRIMARY KEY (user_id, session_id)
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

    public function testRunDeletesOnlyTargetUsersSessionsAndDispatchesSessionTerminatedEvent(): void
    {
        $this->insertSession(1, 'sess-a');
        $this->insertSession(1, 'sess-b');
        $this->insertSession(2, 'sess-c');

        $dispatcher = new TerminateEventCollector();
        $service = new TerminateUserSessionsService($dispatcher);

        $service->run(1);

        self::assertCount(0, UserSessionHistory::query()->where(['user_id' => 1])->all());
        self::assertCount(1, UserSessionHistory::query()->where(['user_id' => 2])->all());

        $events = $dispatcher->events();
        self::assertCount(1, $events);
        self::assertInstanceOf(SessionEvent::class, $events[0]);
        self::assertSame(1, $events[0]->getUserId());
        self::assertSame('', $events[0]->getSessionId());
        self::assertSame(['type' => SessionEvent::SESSION_TERMINATED], $events[0]->getData());
    }

    private function insertSession(int $userId, string $sessionId): void
    {
        $session = new UserSessionHistory();
        $session->setUserId($userId);
        $session->setSessionId($sessionId);
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();
    }
}

final class TerminateEventCollector implements EventDispatcherInterface
{
    /** @var list<object> */
    private array $events = [];

    #[\Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;
        return $event;
    }

    /**
     * @return list<object>
     */
    public function events(): array
    {
        return $this->events;
    }
}
