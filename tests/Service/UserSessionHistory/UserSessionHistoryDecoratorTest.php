<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\UserSessionHistory;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSessionHistory;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\UserSessionHistory\UserSessionHistoryDecorator;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeSession;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class UserSessionHistoryDecoratorTest extends TestCase
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

    public function testPruneDoesNotDeleteSessionsOfOtherUsers(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig(enableSessionHistory: true, numberSessionHistory: 2);

        $session = $this->createOpenSession('sessb');
        $decorator = new UserSessionHistoryDecorator($eventDispatcher, $config, $session);

        $userA = $this->createUser('usera', 'usera@example.com');
        $userIdA = (int) $userA->getId();

        for ($i = 0; $i < 3; $i++) {
            $this->createSessionHistoryEntry($userIdA, 'a_' . $i, 10 - $i);
        }

        $userB = $this->createUser('userb', 'userb@example.com');

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent';

        $decorator->registerLogin($userB);

        $remainingA = UserSessionHistory::query()->where(['user_id' => $userIdA])->all();
        self::assertCount(3, $remainingA);

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function testRegisterLoginFallsBackToLocalhostWhenNoRemoteAddr(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig(enableSessionHistory: true, disableIpLogging: false);

        $session = $this->createOpenSession('sessfallback');
        $decorator = new UserSessionHistoryDecorator($eventDispatcher, $config, $session);

        $user = $this->createUser('fallback', 'fallback@example.com');

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        $decorator->registerLogin($user);

        $sessions = UserSessionHistory::query()->where(['user_id' => (int) $user->getId()])->all();
        self::assertCount(1, $sessions);
        self::assertSame('127.0.0.1', $sessions[0]->getIp());
        self::assertNull($sessions[0]->getUserAgent());
    }

    public function testRegisterLoginPrunesOldSessionsWhenOverLimit(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig(enableSessionHistory: true, numberSessionHistory: 2);

        $session = $this->createOpenSession('sessnew');
        $decorator = new UserSessionHistoryDecorator($eventDispatcher, $config, $session);

        $user = $this->createUser('prunetest', 'prunetest@example.com');
        $userId = (int) $user->getId();

        for ($i = 0; $i < 3; $i++) {
            $this->createSessionHistoryEntry($userId, 'old_' . $i, 10 - $i);
        }

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent';

        $decorator->registerLogin($user);

        $sessions = UserSessionHistory::query()->where(['user_id' => $userId])->all();
        self::assertCount(2, $sessions);

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function testRegisterLoginWithDisableIpLogging(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig(enableSessionHistory: true, disableIpLogging: true);

        $session = $this->createOpenSession('sess456');
        $decorator = new UserSessionHistoryDecorator($eventDispatcher, $config, $session);

        $user = $this->createUser('test2', 'test2@example.com');

        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent';

        $decorator->registerLogin($user);

        $sessions = UserSessionHistory::query()->where(['user_id' => (int) $user->getId()])->all();
        self::assertCount(1, $sessions);
        self::assertSame('127.0.0.1', $sessions[0]->getIp());

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function testRegisterLoginWithNullUserIdRecordsZero(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig(enableSessionHistory: true);

        $decorator = new UserSessionHistoryDecorator($eventDispatcher, $config);

        $user = new User();
        $user->setUsername('noid');
        $user->setEmail('noid@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');

        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $decorator->registerLogin($user);

        $sessions = UserSessionHistory::query()->where(['user_id' => 0])->all();
        self::assertCount(1, $sessions);
        self::assertSame(0, $sessions[0]->getUserId());

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testRegisterLoginWithNumberSessionHistoryFalse(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig(enableSessionHistory: true, numberSessionHistory: false);

        $session = $this->createOpenSession('sessnoprune');
        $decorator = new UserSessionHistoryDecorator($eventDispatcher, $config, $session);

        $user = $this->createUser('noprunetest', 'noprunetest@example.com');

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $decorator->registerLogin($user);

        $sessions = UserSessionHistory::query()->where(['user_id' => (int) $user->getId()])->all();
        self::assertCount(1, $sessions);

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testRegisterLoginWithSessionHistoryDisabledReturnsEarly(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');
        $config = new ModuleConfig(enableSessionHistory: false);

        $decorator = new UserSessionHistoryDecorator($eventDispatcher, $config);

        $user = $this->createUser('test', 'test@example.com');

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent';

        $decorator->registerLogin($user);

        $sessions = UserSessionHistory::query()->where(['user_id' => (int) $user->getId()])->all();
        self::assertCount(0, $sessions);

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function testRegisterLoginWithSessionHistoryEnabled(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig(enableSessionHistory: true);

        $session = $this->createOpenSession('sess123');
        $decorator = new UserSessionHistoryDecorator($eventDispatcher, $config, $session);

        $user = $this->createUser('test', 'test@example.com');

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent';

        $decorator->registerLogin($user);

        $sessions = UserSessionHistory::query()->where(['user_id' => (int) $user->getId()])->all();
        self::assertCount(1, $sessions);
        self::assertSame('sess123', $sessions[0]->getSessionId());
        self::assertSame('192.168.1.1', $sessions[0]->getIp());
        self::assertSame('TestAgent', $sessions[0]->getUserAgent());
        self::assertSame((int) $user->getId(), $sessions[0]->getUserId());
        self::assertNotSame(0, $sessions[0]->getCreatedAt());
        self::assertNotSame(0, $sessions[0]->getUpdatedAt());

        $event = $eventDispatcher->getEvent(SessionEvent::class);
        self::assertInstanceOf(SessionEvent::class, $event);
        self::assertSame(['type' => SessionEvent::SESSION_CREATED], $event->getData());

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function testRegisterLoginWithSessionNullId(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();
        $config = new ModuleConfig(enableSessionHistory: true);

        $decorator = new UserSessionHistoryDecorator($eventDispatcher, $config);

        $user = $this->createUser('nosess', 'nosess@example.com');

        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $decorator->registerLogin($user);

        $sessions = UserSessionHistory::query()->where(['user_id' => (int) $user->getId()])->all();
        self::assertCount(1, $sessions);
        self::assertSame('', $sessions[0]->getSessionId());

        unset($_SERVER['REMOTE_ADDR']);
    }

    private function createOpenSession(string $id): FakeSession
    {
        $session = new FakeSession();
        $session->setId($id);
        $session->open();

        return $session;
    }

    private function createSessionHistoryEntry(int $userId, string $sessionId, int $ageOffset): UserSessionHistory
    {
        $sh = new UserSessionHistory();
        $sh->setUserId($userId);
        $sh->setSessionId($sessionId);
        $sh->setIp('127.0.0.1');
        $sh->setCreatedAt(time() - $ageOffset);
        $sh->setUpdatedAt(time() - $ageOffset);
        $sh->save();

        return $sh;
    }

    private function createUser(string $username, string $email): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }
}
