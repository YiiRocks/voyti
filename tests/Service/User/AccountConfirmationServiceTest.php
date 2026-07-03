<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\User\AccountConfirmationService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class AccountConfirmationServiceTest extends TestCase
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

    /**
     * Kills the MethodCallRemoval mutant on `$userToken->delete()`: the matching token row
     * must actually be removed from storage on a successful confirmation.
     */
    public function testRunDeletesTokenOnSuccessfulConfirmation(): void
    {
        $user = $this->createUser('carol', false);
        $userId = (int) $user->getId();
        $this->createToken($userId, 'valid-code', UserToken::TYPE_CONFIRMATION, time());

        $service = $this->createService();
        $result = $service->run(
            'valid-code',
            $user,
            new ConfirmationService(new EventCollector(), new UserTokenRepository()),
        );

        self::assertTrue($result);

        $token = UserToken::query()->andWhere(['user_id' => $userId, 'code' => 'valid-code', 'type' => UserToken::TYPE_CONFIRMATION])->one();
        self::assertNull($token);

        $refreshedUser = User::query()->findByPk($userId);
        self::assertTrue($refreshedUser->isConfirmed());
    }

    /**
     * Kills DecrementInteger/IncrementInteger mutants on the fallback user id (0 vs -1/1) used
     * when $user->getId() is null: a token stored under user_id=0 is only found when the
     * fallback truly resolves to 0.
     */
    public function testRunFindsTokenByFallbackZeroIdWhenUserIsNotYetPersisted(): void
    {
        // A detached, unsaved User instance: getId() is null.
        $user = new User();
        $user->setUsername('detached');
        $user->setEmail('detached@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('authkey');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        self::assertNull($user->getId());
        self::assertFalse($user->isConfirmed());

        // Token stored for user_id = 0, matching the fallback used when getId() is null.
        $this->createToken(0, 'zero-fallback-code', UserToken::TYPE_CONFIRMATION, time());

        $service = $this->createService();
        $result = $service->run(
            'zero-fallback-code',
            $user,
            new ConfirmationService(new EventCollector(), new UserTokenRepository()),
        );

        self::assertTrue($result);

        // The user is only assigned a real id during ConfirmationService::run() (on save),
        // so its deleteAllByUserId() call operates on that new id, not on 0 — meaning the
        // user_id=0 token row can only be removed by AccountConfirmationService's own
        // $userToken->delete() call.
        $token = UserToken::query()->andWhere(['user_id' => 0, 'code' => 'zero-fallback-code', 'type' => UserToken::TYPE_CONFIRMATION])->one();
        self::assertNull($token);
    }

    /**
     * Kills the LogicalOr mutant on `$userToken === null || $userToken->getIsExpired()`:
     * a mutant using `&&` would, for a non-null-but-expired token, incorrectly proceed
     * past the guard instead of returning false.
     */
    public function testRunReturnsFalseWhenTokenIsExpired(): void
    {
        $user = $this->createUser('bob', false);
        $userId = (int) $user->getId();
        $expiredCreatedAt = time() - 100000; // TYPE_CONFIRMATION lifespan is 86400 seconds
        $this->createToken($userId, 'expired-code', UserToken::TYPE_CONFIRMATION, $expiredCreatedAt);

        $service = $this->createService();
        $result = $service->run(
            'expired-code',
            $user,
            new ConfirmationService(new EventCollector(), new UserTokenRepository()),
        );

        self::assertFalse($result);

        // Token must still be present since the guard should short-circuit before deletion.
        $token = UserToken::query()->andWhere(['user_id' => $userId, 'code' => 'expired-code', 'type' => UserToken::TYPE_CONFIRMATION])->one();
        self::assertInstanceOf(UserToken::class, $token);
    }

    public function testRunReturnsFalseWhenUserIsAlreadyConfirmed(): void
    {
        $user = $this->createUser('alice', true);
        $service = $this->createService();

        $result = $service->run('some-code', $user, new ConfirmationService(new EventCollector(), new UserTokenRepository()));

        self::assertFalse($result);
    }

    private function createService(): AccountConfirmationService
    {
        return new AccountConfirmationService(new UserTokenRepository());
    }

    private function createToken(int $userId, string $code, int $type, int $createdAt): UserToken
    {
        $token = new UserToken();
        $token->setUserId($userId);
        $token->setCode($code);
        $token->setType($type);
        $token->setCreatedAt($createdAt);
        $token->save();

        return $token;
    }

    private function createUser(string $username, bool $confirmed): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username . '@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('authkey-' . $username);
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        if ($confirmed) {
            $user->setConfirmedAt(time());
        }
        $user->save();

        return $user;
    }
}

final class EventCollector implements EventDispatcherInterface
{
    #[\Override]
    public function dispatch(object $event): object
    {
        return $event;
    }
}
