<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Entity;

use YiiRocks\Voyti\Entity\UserSessionHistory;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserSessionHistoryTest extends TestCase
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

    public function testCompositePrimaryKey(): void
    {
        $session1 = new UserSessionHistory();
        $session1->setUserId(1);
        $session1->setSessionId('sess_one');
        $session1->setCreatedAt(time());
        $session1->setUpdatedAt(time());
        $session1->save();

        $session2 = new UserSessionHistory();
        $session2->setUserId(1);
        $session2->setSessionId('sess_two');
        $session2->setCreatedAt(time());
        $session2->setUpdatedAt(time());
        $session2->save();

        $session3 = new UserSessionHistory();
        $session3->setUserId(2);
        $session3->setSessionId('sess_one');
        $session3->setCreatedAt(time());
        $session3->setUpdatedAt(time());
        $session3->save();

        $this->assertSame(2, UserSessionHistory::query()->where(['user_id' => 1])->count());
        $this->assertSame(1, UserSessionHistory::query()->where(['session_id' => 'sess_two'])->count());
    }

    public function testCreateAndFind(): void
    {
        $session = new UserSessionHistory();
        $session->setUserId(1);
        $session->setSessionId('sess_abc123');
        $session->setUserAgent('Mozilla/5.0');
        $session->setIp('192.168.1.1');
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();

        $found = UserSessionHistory::query()->where(['user_id' => 1, 'session_id' => 'sess_abc123'])->one();
        $this->assertInstanceOf(UserSessionHistory::class, $found);
        $this->assertSame(1, $found->getUserId());
        $this->assertSame('sess_abc123', $found->getSessionId());
        $this->assertSame('Mozilla/5.0', $found->getUserAgent());
        $this->assertSame('192.168.1.1', $found->getIp());
    }

    public function testDeleteSession(): void
    {
        $session = new UserSessionHistory();
        $session->setUserId(5);
        $session->setSessionId('delete_test');
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();

        $session->delete();

        $found = UserSessionHistory::query()->where(['user_id' => 5, 'session_id' => 'delete_test'])->one();
        $this->assertNull($found);
    }

    public function testNullUserAgentAndIp(): void
    {
        $session = new UserSessionHistory();
        $this->assertNull($session->getUserAgent());
        $this->assertNull($session->getIp());

        $session->setUserId(3);
        $session->setSessionId('null_test');
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();

        $found = UserSessionHistory::query()->where(['session_id' => 'null_test'])->one();
        $this->assertInstanceOf(UserSessionHistory::class, $found);
        $this->assertNull($found->getUserAgent());
        $this->assertNull($found->getIp());
    }

    public function testTimestamps(): void
    {
        $now = time();
        $later = $now + 3600;

        $session = new UserSessionHistory();
        $session->setUserId(6);
        $session->setSessionId('time_test');
        $session->setCreatedAt($now);
        $session->setUpdatedAt($now);
        $session->save();

        $session->setUpdatedAt($later);
        $session->save();

        $found = UserSessionHistory::query()->where(['session_id' => 'time_test'])->one();
        $this->assertInstanceOf(UserSessionHistory::class, $found);
        $this->assertEquals($now, $found->getCreatedAt());
        $this->assertEquals($later, $found->getUpdatedAt());
    }

    public function testUpdateSession(): void
    {
        $session = new UserSessionHistory();
        $session->setUserId(4);
        $session->setSessionId('update_test');
        $session->setIp('10.0.0.1');
        $session->setCreatedAt(time());
        $session->setUpdatedAt(time());
        $session->save();

        $session->setIp('10.0.0.2');
        $session->setUserAgent('NewBrowser/1.0');
        $session->setUpdatedAt(time());
        $session->save();

        $found = UserSessionHistory::query()->where(['user_id' => 4, 'session_id' => 'update_test'])->one();
        $this->assertInstanceOf(UserSessionHistory::class, $found);
        $this->assertSame('10.0.0.2', $found->getIp());
        $this->assertSame('NewBrowser/1.0', $found->getUserAgent());
    }
}
