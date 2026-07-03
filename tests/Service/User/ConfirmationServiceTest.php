<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class ConfirmationServiceTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConnectionProvider::set($this->getDb());
        $db = $this->getDb();
        $db->createCommand('CREATE TABLE {{%user}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            auth_key VARCHAR(255) NOT NULL,
            unconfirmed_email VARCHAR(255),
            registration_ip VARCHAR(45),
            flags INTEGER NOT NULL DEFAULT 0,
            confirmed_at INTEGER,
            blocked_at INTEGER,
            updated_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL,
            last_login_at INTEGER,
            auth_tf_key VARCHAR(64),
            auth_tf_enabled INTEGER DEFAULT 0,
            password_changed_at INTEGER,
            last_login_ip VARCHAR(45),
            gdpr_deleted INTEGER DEFAULT 0,
            gdpr_consent INTEGER DEFAULT 0,
            gdpr_consent_date INTEGER,
            auth_tf_type VARCHAR(20)
        )')->execute();
        $db->createCommand('CREATE TABLE {{%user_token}} (
            user_id INTEGER NOT NULL,
            code VARCHAR(32) NOT NULL,
            type SMALLINT NOT NULL,
            created_at INTEGER NOT NULL,
            PRIMARY KEY (user_id, code, type)
        )')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_token}}')->execute();
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testGetUserIdUsesZeroFallbackWhenUserHasNoId(): void
    {
        // A user that was never persisted has a null id; the private getUserId()
        // fallback must be exactly 0 (not -1 or 1). save() always populates the
        // primary key on insert, so the null branch cannot be observed by going
        // through run(); we invoke the private method directly via reflection.
        $user = new User();
        $user->setUsername('unsaved');
        $user->setEmail('unsaved@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());

        self::assertNull($user->getId());

        $service = new ConfirmationService(new ConfirmationServiceEventCollector(), new UserTokenRepository());

        $method = new \ReflectionMethod(ConfirmationService::class, 'getUserId');
        $result = $method->invoke($service, $user);

        self::assertSame(0, $result);
    }

    public function testRunConfirmsUserDeletesTokensAndDispatchesTwoEventsWithSameUser(): void
    {
        $user = $this->createUser(confirmedAt: null);
        $userId = (int) $user->getId();

        // Seed tokens for this user and another user, to verify only this user's tokens are deleted.
        $this->insertToken($userId, 'code-a', UserToken::TYPE_CONFIRMATION);
        $this->insertToken($userId, 'code-b', UserToken::TYPE_RECOVERY);
        $otherUser = $this->createUser(confirmedAt: null, username: 'other', email: 'other@example.com');
        $otherUserId = (int) $otherUser->getId();
        $this->insertToken($otherUserId, 'code-c', UserToken::TYPE_CONFIRMATION);

        $dispatcher = new ConfirmationServiceEventCollector();
        $service = new ConfirmationService($dispatcher, new UserTokenRepository());

        $before = time();
        $result = $service->run($user);
        $after = time();

        self::assertTrue($result);
        self::assertGreaterThanOrEqual($before, $user->getConfirmedAt());
        self::assertLessThanOrEqual($after, $user->getConfirmedAt());

        $reloaded = User::query()->findByPk($userId);
        self::assertTrue($reloaded->isConfirmed());

        // Only the confirmed user's tokens were deleted; the other user's token remains.
        self::assertCount(0, (new UserTokenRepository())->findByUserId($userId));
        self::assertCount(1, (new UserTokenRepository())->findByUserId($otherUserId));

        $events = $dispatcher->events();
        self::assertCount(2, $events);
        self::assertInstanceOf(UserEvent::class, $events[0]);
        self::assertInstanceOf(UserEvent::class, $events[1]);
        self::assertSame($user, $events[0]->getUser());
        self::assertSame($user, $events[1]->getUser());
    }

    public function testRunReturnsFalseAndDispatchesNothingWhenUserAlreadyConfirmed(): void
    {
        $user = $this->createUser(confirmedAt: 12345);
        $dispatcher = new ConfirmationServiceEventCollector();
        $service = new ConfirmationService($dispatcher, new UserTokenRepository());

        $result = $service->run($user);

        self::assertFalse($result);
        self::assertSame(12345, $user->getConfirmedAt());
        self::assertCount(0, $dispatcher->events());
    }

    private function createUser(?int $confirmedAt, string $username = 'alice', string $email = 'alice@example.com'): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $now = time();
        $user->setCreatedAt($now);
        $user->setUpdatedAt($now);
        $user->setConfirmedAt($confirmedAt);
        $user->save();

        return $user;
    }

    private function insertToken(int $userId, string $code, int $type): void
    {
        $this->getDb()->createCommand()->insert('{{%user_token}}', [
            'user_id' => $userId,
            'code' => $code,
            'type' => $type,
            'created_at' => time(),
        ])->execute();
    }
}

final class ConfirmationServiceEventCollector implements EventDispatcherInterface
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
