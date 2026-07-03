<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Event\Security\ResetPasswordEvent;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\Password\ResetService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Security\PasswordHasher;

final class ResetServiceTest extends TestCase
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

    public function testRunWithoutTokenUpdatesPasswordAndDispatchesTwoUserEvents(): void
    {
        $user = $this->createUser();
        $before = time();

        $dispatcher = new ResetServiceEventCollector();
        $hasher = $this->createHasher();
        $service = $this->createService($dispatcher, $hasher);

        $result = $service->run('new-secret-password', $user);
        $after = time();

        self::assertTrue($result);

        $reloaded = User::query()->findByPk($user->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertTrue($hasher->validate('new-secret-password', $reloaded->getPasswordHash()));
        self::assertGreaterThanOrEqual($before, $reloaded->getPasswordChangedAt());
        self::assertLessThanOrEqual($after, $reloaded->getPasswordChangedAt());
        self::assertGreaterThanOrEqual($before, $reloaded->getUpdatedAt());
        self::assertLessThanOrEqual($after, $reloaded->getUpdatedAt());

        $events = $dispatcher->events();
        self::assertCount(2, $events);
        self::assertInstanceOf(UserEvent::class, $events[0]);
        self::assertInstanceOf(UserEvent::class, $events[1]);
        self::assertSame($user->getEmail(), $events[0]->getUser()->getEmail());
        self::assertSame($user->getEmail(), $events[1]->getUser()->getEmail());
    }

    public function testRunWithTokenDeletesTokenAndDispatchesResetPasswordEvent(): void
    {
        $user = $this->createUser();
        $token = new UserToken();
        $token->setUserId((int) $user->getId());
        $token->setCode('reset-code-1234567890123456');
        $token->setType(UserToken::TYPE_RECOVERY);
        $token->setCreatedAt(time());
        $token->save();

        self::assertCount(1, UserToken::query()->all());

        $dispatcher = new ResetServiceEventCollector();
        $hasher = $this->createHasher();
        $service = $this->createService($dispatcher, $hasher);

        $result = $service->run('another-secret', $user, $token);

        self::assertTrue($result);
        self::assertCount(0, UserToken::query()->all());

        $events = $dispatcher->events();
        self::assertCount(3, $events);
        self::assertInstanceOf(UserEvent::class, $events[0]);
        self::assertInstanceOf(ResetPasswordEvent::class, $events[1]);
        self::assertInstanceOf(UserEvent::class, $events[2]);
        self::assertSame($token, $events[1]->getToken());
    }

    private function createHasher(): PasswordHasher
    {
        return new PasswordHasher(PASSWORD_BCRYPT, ['cost' => 4]);
    }

    private function createService(ResetServiceEventCollector $dispatcher, PasswordHasher $hasher): ResetService
    {
        return new ResetService(
            $hasher,
            ModuleConfig::fromArray([]),
            $dispatcher,
            new UserTokenRepository(),
        );
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setUsername('resetuser');
        $user->setEmail('reset@example.com');
        $user->setPasswordHash('old-hash');
        $user->setAuthKey('auth-key');
        $user->setCreatedAt(time() - 100);
        $user->setUpdatedAt(time() - 100);
        $user->save();

        return $user;
    }
}

final class ResetServiceEventCollector implements EventDispatcherInterface
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
