<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Auth;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserSocialAccountRepository;
use YiiRocks\Voyti\Service\Auth\UserSocialAuthenticateService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;

final class UserSocialAuthenticateServiceTest extends TestCase
{
    private UserSocialAccountRepository $accounts;
    private UserRepository $users;

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
            password_hash VARCHAR(60) NOT NULL,
            auth_key VARCHAR(32) NOT NULL,
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
        $this->users = new UserRepository();
        $this->accounts = new UserSocialAccountRepository();
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

    public function testRunCastsOauthUserIdToStringForClientId(): void
    {
        $session = $this->session();
        $session->set('oauth_client_data', ['user_id' => 12345, 'email' => 'oauth-int@example.com']);
        $events = [];
        [$service] = $this->service(ModuleConfig::fromArray([]), $session, $events);

        $result = $service->run('github', '', []);
        $account = $this->accounts->findByProviderAndClientId('github', '12345');

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(UserSocialAccount::class, $account);
        $this->assertSame('12345', $account->getClientId());
    }

    public function testRunFailsForBlockedAssociatedUser(): void
    {
        $session = $this->session();
        $events = [];
        $user = $this->createUser('blocked-user', 'blocked@example.com');
        $user->setBlockedAt(time());
        $user->save();
        $this->createAccount('github', 'client-123', (int) $user->getId(), 'code-123');
        [$service] = $this->service(ModuleConfig::fromArray([]), $session, $events);

        $result = $service->run('github', 'client-123', []);

        $this->assertTrue($result->isFailure());
        $this->assertSame('Your account has been blocked', $result->getMessage());
    }

    public function testRunFailsWhenAssociatedUserCannotBeFound(): void
    {
        $session = $this->session();
        $events = [];
        $this->createAccount('github', 'client-123', 999, 'code-123');
        [$service] = $this->service(ModuleConfig::fromArray([]), $session, $events);

        $result = $service->run('github', 'client-123', []);

        $this->assertTrue($result->isFailure());
        $this->assertSame('Associated user not found', $result->getMessage());
    }

    public function testRunFailsWhenClientIdCannotBeDetermined(): void
    {
        $session = $this->session();
        $events = [];
        [$service] = $this->service(ModuleConfig::fromArray([]), $session, $events);

        $result = $service->run('github', '', []);

        $this->assertTrue($result->isFailure());
        $this->assertSame('Unable to determine social network client ID', $result->getMessage());
    }

    public function testRunFailsWhenSocialRegistrationDisabled(): void
    {
        $session = $this->session();
        $events = [];
        [$service] = $this->service(
            ModuleConfig::fromArray(['enableSocialNetworkRegistration' => false]),
            $session,
            $events,
        );

        $result = $service->run('github', '123', []);

        $this->assertTrue($result->isFailure());
        $this->assertSame('Social network registration is disabled', $result->getMessage());
    }

    public function testRunGeneratesThirtyTwoCharacterCodeForNewAccount(): void
    {
        $session = $this->session();
        $events = [];
        [$service] = $this->service(ModuleConfig::fromArray([]), $session, $events);

        $result = $service->run('github', 'client-code-length', ['username' => 'gh-user']);
        $account = $this->accounts->findByProviderAndClientId('github', 'client-code-length');

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(UserSocialAccount::class, $account);
        $this->assertSame(32, strlen($account->getCode()));
    }

    public function testRunIgnoresNonArrayOauthClientData(): void
    {
        $session = $this->session();
        $session->set('oauth_client_data', 'not-an-array');
        $events = [];
        [$service] = $this->service(ModuleConfig::fromArray([]), $session, $events);

        $result = $service->run('github', '', []);

        $this->assertTrue($result->isFailure());
        $this->assertSame('Unable to determine social network client ID', $result->getMessage());
    }

    public function testRunLinksExistingUserByEmailAndLogsThemIn(): void
    {
        $session = $this->session();
        $events = [];
        $user = $this->createUser('known-user', 'known@example.com');
        [$service, $currentUser] = $this->service(ModuleConfig::fromArray([]), $session, $events);

        $result = $service->run(
            'github',
            'client-123',
            ['email' => 'known@example.com', 'username' => 'gh-user'],
            ['REMOTE_ADDR' => '10.0.0.5'],
        );

        $linkedAccount = $this->accounts->findByProviderAndClientId('github', 'client-123');
        $reloadedUser = $this->users->findById((int) $user->getId());

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(UserSocialAccount::class, $linkedAccount);
        $this->assertSame((int) $user->getId(), $linkedAccount->getUserId());
        $this->assertNull($linkedAccount->getCode());
        $this->assertSame((string) $user->getId(), $currentUser->getId());
        $this->assertInstanceOf(User::class, $reloadedUser);
        $this->assertNotNull($reloadedUser->getLastLoginAt());
        $this->assertSame('10.0.0.5', $reloadedUser->getLastLoginIp());
        $this->assertTrue(
            array_any($events, static fn (object $event): bool => $event instanceof AfterLoginEvent),
        );
    }

    public function testRunRemovesOauthClientDataAfterSuccessfulLogin(): void
    {
        $session = $this->session();
        $session->set('oauth_client_data', ['user_id' => 'client-123', 'email' => 'known@example.com']);
        $events = [];
        $user = $this->createUser('known-user', 'known@example.com');
        $this->createAccount('github', 'client-123', (int) $user->getId(), 'code-123');
        [$service] = $this->service(ModuleConfig::fromArray([]), $session, $events);

        $result = $service->run('github', 'client-123', []);

        $this->assertTrue($result->isSuccess());
        $this->assertNull($session->get('oauth_client_data'));
    }

    public function testRunSetsCreatedAtTimestampForNewAccount(): void
    {
        $session = $this->session();
        $events = [];
        [$service] = $this->service(ModuleConfig::fromArray([]), $session, $events);
        $before = time();

        $result = $service->run('github', 'client-created-at', ['username' => 'gh-user']);
        $account = $this->accounts->findByProviderAndClientId('github', 'client-created-at');

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(UserSocialAccount::class, $account);
        $this->assertGreaterThanOrEqual($before, $account->getCreatedAt());
    }

    public function testRunStoresConnectionCodeForUnlinkedAccount(): void
    {
        $session = $this->session();
        $events = [];
        [$service] = $this->service(ModuleConfig::fromArray([]), $session, $events);

        $result = $service->run(
            'github',
            'client-123',
            ['username' => 'gh-user', 'name' => 'GH User'],
        );

        $account = $this->accounts->findByProviderAndClientId('github', 'client-123');

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(UserSocialAccount::class, $account);
        $this->assertNull($account->getUserId());
        $this->assertSame($account->getCode(), $session->get('social_network_account_code'));
        $this->assertSame('gh-user', $account->getUsername());
        $this->assertSame(json_encode(['username' => 'gh-user', 'name' => 'GH User'], JSON_THROW_ON_ERROR), $account->getData());
    }

    public function testRunTreatsNonStringUsernameAttributeAsMissing(): void
    {
        $session = $this->session();
        $events = [];
        [$service] = $this->service(ModuleConfig::fromArray([]), $session, $events);

        $result = $service->run('github', 'client-non-string-username', ['username' => 42]);
        $account = $this->accounts->findByProviderAndClientId('github', 'client-non-string-username');

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(UserSocialAccount::class, $account);
        $this->assertNull($account->getUsername());
    }

    public function testRunUsesMaskedIpWhenIpLoggingDisabled(): void
    {
        $session = $this->session();
        $events = [];
        $user = $this->createUser('known-user', 'known@example.com');
        $this->createAccount('github', 'client-123', (int) $user->getId(), 'code-123');
        [$service] = $this->service(
            ModuleConfig::fromArray(['disableIpLogging' => true]),
            $session,
            $events,
        );

        $result = $service->run('github', 'client-123', [], ['REMOTE_ADDR' => '10.0.0.5']);
        $reloadedUser = $this->users->findById((int) $user->getId());

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(User::class, $reloadedUser);
        $this->assertSame('127.0.0.1', $reloadedUser->getLastLoginIp());
    }

    public function testRunUsesOauthClientDataWhenClientIdIsMissing(): void
    {
        $session = $this->session();
        $session->set('oauth_client_data', ['user_id' => 'oauth-user', 'email' => 'oauth@example.com']);
        $events = [];
        [$service] = $this->service(ModuleConfig::fromArray([]), $session, $events);

        $result = $service->run('github', '', ['username' => 'merged-user']);
        $account = $this->accounts->findByProviderAndClientId('github', 'oauth-user');

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(UserSocialAccount::class, $account);
        $this->assertSame('oauth@example.com', $account->getEmail());
        $this->assertSame('merged-user', $account->getUsername());
    }

    private function createAccount(string $provider, string $clientId, ?int $userId, ?string $code): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setProvider($provider);
        $account->setClientId($clientId);
        $account->setUserId($userId);
        $account->setCode($code);
        $account->setCreatedAt(time());
        $account->save();
        return $account;
    }

    private function createUser(string $username, string $email): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash1');
        $user->setAuthKey('auth1');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();
        return $user;
    }

    /**
     * @param list<object> $events
     *
     * @return array{0: UserSocialAuthenticateService, 1: CurrentUser}
     */
    private function service(ModuleConfig $config, SessionInterface $session, array &$events): array
    {
        $dispatcher = new class($events) implements EventDispatcherInterface {
            /** @param list<object> $events */
            public function __construct(private array &$events)
            {
            }

            #[\Override]
            public function dispatch(object $event): object
            {
                $this->events[] = $event;
                return $event;
            }
        };

        $currentUser = (new CurrentUser(
            new class($this->users) implements IdentityRepositoryInterface {
                public function __construct(private readonly UserRepository $users)
                {
                }

                #[\Override]
                public function findIdentity(string $id): ?IdentityInterface
                {
                    return $this->users->findById((int) $id);
                }
            },
            $dispatcher,
        ))->withSession($session);

        $service = new UserSocialAuthenticateService(
            $config,
            $this->accounts,
            $this->users,
            $currentUser,
            $session,
            $dispatcher,
        );

        return [$service, $currentUser];
    }

    private function session(): SessionInterface
    {
        return new class implements SessionInterface {
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
            }
            #[\Override]
            public function destroy(): void
            {
                $this->values = [];
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
                return 'test-session';
            }
            #[\Override]
            public function getName(): string
            {
                return 'TESTSESSID';
            }
            #[\Override]
            public function has(string $key): bool
            {
                return array_key_exists($key, $this->values);
            }
            #[\Override]
            public function isActive(): bool
            {
                return true;
            }
            #[\Override]
            public function open(): void
            {
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
        };
    }
}
