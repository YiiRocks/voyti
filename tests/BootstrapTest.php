<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Login\Cookie\CookieLoginIdentityInterface;

final class BootstrapTest extends TestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        ConnectionProvider::clear();
        parent::tearDown();
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testBootstrapAllowsCurrentUserToRestoreIdentityFromCookie(): void
    {
        $bootstrap = require dirname(__DIR__) . '/config/bootstrap.php';
        $session = new TestSession();
        ConnectionProvider::set($this->getDb());
        $identityRepository = new class implements IdentityRepositoryInterface {
            public function findIdentity(string $id): ?IdentityInterface
            {
                return $id === '42' ? new TestIdentity($id, 'cookie-key') : null;
            }
        };
        $currentUser = new CurrentUser(
            $identityRepository,
            new class implements EventDispatcherInterface {
                public function dispatch(object $event): object
                {
                    return $event;
                }
            },
        );
        $rememberMeCookieService = new RememberMeCookieService(3600);

        $container = new class($session, $currentUser, $identityRepository, $rememberMeCookieService) implements ContainerInterface {
            public function __construct(
                private readonly SessionInterface $session,
                private readonly CurrentUser $currentUser,
                private readonly IdentityRepositoryInterface $identityRepository,
                private readonly RememberMeCookieService $rememberMeCookieService,
            ) {
            }

            public function get(string $id): mixed
            {
                return match ($id) {
                    SessionInterface::class => $this->session,
                    CurrentUser::class => $this->currentUser,
                    IdentityRepositoryInterface::class => $this->identityRepository,
                    RememberMeCookieService::class => $this->rememberMeCookieService,
                    default => null,
                };
            }

            public function has(string $id): bool
            {
                return in_array(
                    $id,
                    [SessionInterface::class, CurrentUser::class, IdentityRepositoryInterface::class, RememberMeCookieService::class],
                    true,
                );
            }
        };

        ConnectionProvider::set($this->createMock(ConnectionInterface::class));

        $cookieName = $rememberMeCookieService->getCookieName();
        $previousCookie = $_COOKIE[$cookieName] ?? null;
        $_COOKIE[$cookieName] = json_encode(['42', 'cookie-key', time() + 3600], JSON_THROW_ON_ERROR);

        try {
            $bootstrap[0]($container);
        } finally {
            if ($previousCookie === null) {
                unset($_COOKIE[$cookieName]);
            } else {
                $_COOKIE[$cookieName] = $previousCookie;
            }
        }

        $this->assertSame('42', $currentUser->getId());
        $this->assertSame('42', $session->get('__auth_id'));
    }

    public function testBootstrapAllowsCurrentUserToRestoreIdentityFromSession(): void
    {
        $bootstrap = require dirname(__DIR__) . '/config/bootstrap.php';
        $session = new TestSession();
        $session->set('__auth_id', '42');
        $currentUser = new CurrentUser(
            new class implements IdentityRepositoryInterface {
                public function findIdentity(string $id): ?IdentityInterface
                {
                    return $id === '42' ? new TestIdentity($id) : null;
                }
            },
            new class implements EventDispatcherInterface {
                public function dispatch(object $event): object
                {
                    return $event;
                }
            },
        );

        $container = new class($session, $currentUser) implements ContainerInterface {
            public function __construct(
                private readonly SessionInterface $session,
                private readonly CurrentUser $currentUser,
            ) {
            }

            public function get(string $id): mixed
            {
                return match ($id) {
                    SessionInterface::class => $this->session,
                    CurrentUser::class => $this->currentUser,
                    default => null,
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, [SessionInterface::class, CurrentUser::class], true);
            }
        };

        $bootstrap[0]($container);
        $this->assertSame('42', $currentUser->getId());
    }

    public function testBootstrapInitializesSessionFromCookie(): void
    {
        $bootstrap = require dirname(__DIR__) . '/config/bootstrap.php';
        $session = new TestSession();

        $container = new class($session) implements ContainerInterface {
            public function __construct(
                private readonly SessionInterface $session,
            ) {
            }

            public function get(string $id): mixed
            {
                return $id === SessionInterface::class ? $this->session : null;
            }

            public function has(string $id): bool
            {
                return $id === SessionInterface::class;
            }
        };

        $previousCookie = $_COOKIE['TESTSESSID'] ?? null;
        $_COOKIE['TESTSESSID'] = 'cookie-session-id';

        try {
            $bootstrap[0]($container);
        } finally {
            if ($previousCookie === null) {
                unset($_COOKIE['TESTSESSID']);
            } else {
                $_COOKIE['TESTSESSID'] = $previousCookie;
            }
        }

        $this->assertSame('cookie-session-id', $session->getId());
        $this->assertTrue($session->opened);
    }

    public function testBootstrapInjectsSessionIntoCurrentUser(): void
    {
        $bootstrap = require dirname(__DIR__) . '/config/bootstrap.php';
        $session = new TestSession();
        $currentUser = new CurrentUser(
            new class implements IdentityRepositoryInterface {
                public function findIdentity(string $id): ?IdentityInterface
                {
                    return new TestIdentity($id);
                }
            },
            new class implements EventDispatcherInterface {
                public function dispatch(object $event): object
                {
                    return $event;
                }
            },
        );

        $container = new class($session, $currentUser) implements ContainerInterface {
            public function __construct(
                private readonly SessionInterface $session,
                private readonly CurrentUser $currentUser,
            ) {
            }

            public function get(string $id): mixed
            {
                return match ($id) {
                    SessionInterface::class => $this->session,
                    CurrentUser::class => $this->currentUser,
                    default => null,
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, [SessionInterface::class, CurrentUser::class], true);
            }
        };

        $bootstrap[0]($container);
        $this->assertTrue($currentUser->login(new TestIdentity('42')));
        $this->assertSame('42', $session->get('__auth_id'));
    }
}

final class TestSession implements SessionInterface
{
    public ?string $id = null;
    public bool $opened = false;
    /** @var array<string, mixed> */
    private array $values = [];

    public function all(): array
    {
        return $this->values;
    }

    public function clear(): void
    {
        $this->values = [];
    }

    public function close(): void
    {
    }

    public function destroy(): void
    {
    }

    public function discard(): void
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function getCookieParameters(): array
    {
        return [];
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return 'TESTSESSID';
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function isActive(): bool
    {
        return $this->opened;
    }

    public function open(): void
    {
        $this->opened = true;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    public function regenerateId(): void
    {
        $this->id = 'test-session-regenerated';
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function setId(string $sessionId): void
    {
        $this->id = $sessionId;
    }
}

final class TestIdentity implements CookieLoginIdentityInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $cookieLoginKey = 'test-auth-key',
    ) {
    }

    public function getCookieLoginKey(): string
    {
        return $this->cookieLoginKey;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function validateCookieLoginKey(string $key): bool
    {
        return $this->cookieLoginKey === $key;
    }
}
