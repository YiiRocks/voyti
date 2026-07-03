<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Auth;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Session\SessionInterface;

final class PendingSocialAccountServiceTest extends TestCase
{
    private const SESSION_KEY = 'social_network_account_code';

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
        $db->createCommand('CREATE TABLE {{%user_social_account}} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            provider VARCHAR(255) NOT NULL,
            client_id VARCHAR(255) NOT NULL,
            code VARCHAR(32),
            email VARCHAR(255),
            username VARCHAR(255),
            data TEXT,
            created_at INTEGER NOT NULL
        )')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_social_account}}')->execute();
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testClearIsPubliclyCallableAndRemovesSessionValue(): void
    {
        $session = new FakePendingAccountSession();
        $session->set(self::SESSION_KEY, 'some-code');
        $service = $this->createService($session);

        $service->clear();

        self::assertNull($session->get(self::SESSION_KEY));
    }

    public function testConnectConnectsAccountAndClearsSessionWhenNoConflict(): void
    {
        $user = $this->createUser('alice');
        $account = $this->createSocialAccount('github', 'client-1', 'code-abc', null);

        $session = new FakePendingAccountSession();
        $session->set(self::SESSION_KEY, 'code-abc');
        $service = $this->createService($session);

        $result = $service->connect($user);

        self::assertTrue($result->isSuccess());
        self::assertNull($session->get(self::SESSION_KEY));

        $reloaded = UserSocialAccount::query()->findByPk($account->getId());
        self::assertSame((int) $user->getId(), $reloaded->getUserId());
    }

    public function testConnectReturnsSuccessWhenNoPendingAccount(): void
    {
        $user = $this->createUser('nobody');
        $session = new FakePendingAccountSession();
        $service = $this->createService($session);

        $result = $service->connect($user);

        self::assertTrue($result->isSuccess());
    }

    public function testGetPendingAccountReturnsNullAndClearsSessionWhenAccountAlreadyConnected(): void
    {
        $owner = $this->createUser('owner');
        $account = $this->createSocialAccount('github', 'client-2', 'code-connected', (int) $owner->getId());

        $session = new FakePendingAccountSession();
        $session->set(self::SESSION_KEY, $account->getCode());
        $service = $this->createService($session);

        $result = $service->getPendingAccount();

        self::assertNull($result);
        self::assertNull($session->get(self::SESSION_KEY));
    }

    public function testGetPendingAccountReturnsNullWithoutClearingSessionWhenCodeIsEmptyString(): void
    {
        $session = new FakePendingAccountSession();
        $session->set(self::SESSION_KEY, '');
        $service = $this->createService($session);

        $result = $service->getPendingAccount();

        self::assertNull($result);
        self::assertTrue($session->has(self::SESSION_KEY));
        self::assertSame('', $session->get(self::SESSION_KEY));
    }

    public function testUseCodeReturnsNullAndClearsSessionWhenAccountAlreadyConnected(): void
    {
        $owner = $this->createUser('owner2');
        $account = $this->createSocialAccount('github', 'client-3', 'code-connected-2', (int) $owner->getId());

        $session = new FakePendingAccountSession();
        $session->set(self::SESSION_KEY, 'leftover');
        $service = $this->createService($session);

        $result = $service->useCode((string) $account->getCode());

        self::assertNull($result);
        self::assertNull($session->get(self::SESSION_KEY));
    }

    public function testUseCodeStoresCodeInSessionForUnconnectedAccount(): void
    {
        $account = $this->createSocialAccount('github', 'client-4', 'code-fresh', null);

        $session = new FakePendingAccountSession();
        $service = $this->createService($session);

        $result = $service->useCode('code-fresh');

        self::assertInstanceOf(UserSocialAccount::class, $result);
        self::assertSame('code-fresh', $session->get(self::SESSION_KEY));
    }

    private function createService(SessionInterface $session): PendingSocialAccountService
    {
        return new PendingSocialAccountService(new UserSocialAccountRepository(), $session);
    }

    private function createSocialAccount(
        string $provider,
        string $clientId,
        string $code,
        ?int $userId,
    ): UserSocialAccount {
        $account = new UserSocialAccount();
        $account->setProvider($provider);
        $account->setClientId($clientId);
        $account->setCode($code);
        $account->setCreatedAt(time());
        if ($userId !== null) {
            $account->setUserId($userId);
        }
        $account->save();

        return $account;
    }

    private function createUser(string $username): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username . '@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('auth-key');
        $user->setUpdatedAt(time());
        $user->setCreatedAt(time());
        $user->save();

        return $user;
    }
}

final class FakePendingAccountSession implements SessionInterface
{
    private bool $active = true;
    /** @var array<string, mixed> */
    private array $values = [];

    #[\Override]
    public function all(): array
    {
        return $this->values;
    }

    #[\Override]
    public function clear(): void
    {
        $this->values = [];
    }

    #[\Override]
    public function close(): void
    {
        $this->active = false;
    }

    #[\Override]
    public function destroy(): void
    {
        $this->values = [];
        $this->active = false;
    }

    #[\Override]
    public function discard(): void
    {
        $this->values = [];
    }

    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    #[\Override]
    public function getCookieParameters(): array
    {
        return [];
    }

    #[\Override]
    public function getId(): ?string
    {
        return 'fake-session';
    }

    #[\Override]
    public function getName(): string
    {
        return 'FAKESESSID';
    }

    #[\Override]
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    #[\Override]
    public function isActive(): bool
    {
        return $this->active;
    }

    #[\Override]
    public function open(): void
    {
        $this->active = true;
    }

    #[\Override]
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    #[\Override]
    public function regenerateId(): void
    {
    }

    #[\Override]
    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    #[\Override]
    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    #[\Override]
    public function setId(string $sessionId): void
    {
    }
}
