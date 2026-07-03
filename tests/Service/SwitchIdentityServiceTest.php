<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\IdentityRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Service\SwitchIdentityService;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Event\AfterLogin;
use Yiisoft\User\Event\AfterLogout;
use Yiisoft\User\Event\BeforeLogin;
use Yiisoft\User\Event\BeforeLogout;

final class SwitchIdentityServiceTest extends TestCase
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
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->hasSqliteConnection()) {
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testRunDispatchesLogoutThenLoginEventsWhenSwitchingFromLoggedInUser(): void
    {
        $userRepository = new UserRepository();
        $admin = $this->createUser($userRepository, 'admin', 'admin@example.test');
        $target = $this->createUser($userRepository, 'target', 'target@example.test');

        $events = new EventCollector();
        $session = new InMemorySession();
        $currentUser = (new CurrentUser(new IdentityRepository($userRepository), $events))->withSession($session);
        $currentUser->login($admin);
        // The login above also dispatches events; clear them so we only observe switchIdentity's own dispatches.
        $events->clear();

        $service = new SwitchIdentityService(
            new ModuleConfig(),
            $userRepository,
            $currentUser,
            $session,
        );

        $result = $service->run((int) $target->getId());

        self::assertTrue($result->isSuccess());
        self::assertSame((string) $target->getId(), $currentUser->getId());

        $dispatched = $events->events();
        $classNames = array_map(static fn (object $event): string => $event::class, $dispatched);

        self::assertSame(
            [BeforeLogout::class, AfterLogout::class, BeforeLogin::class, AfterLogin::class],
            $classNames,
        );
    }

    public function testRunKeepsOriginalIdentityInSessionAfterSwitching(): void
    {
        $userRepository = new UserRepository();
        $admin = $this->createUser($userRepository, 'admin2', 'admin2@example.test');
        $target = $this->createUser($userRepository, 'target2', 'target2@example.test');

        $events = new EventCollector();
        $session = new InMemorySession();
        $currentUser = (new CurrentUser(new IdentityRepository($userRepository), $events))->withSession($session);
        $currentUser->login($admin);

        $service = new SwitchIdentityService(
            new ModuleConfig(),
            $userRepository,
            $currentUser,
            $session,
        );

        $result = $service->run((int) $target->getId());

        self::assertTrue($result->isSuccess());
        self::assertSame((string) $target->getId(), $currentUser->getId());
        self::assertSame((string) $admin->getId(), $session->get('voyti_original_user'));
    }

    private function createUser(UserRepository $userRepository, string $username, string $email): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $userRepository->save($user);

        return $user;
    }
}

final class EventCollector implements EventDispatcherInterface
{
    /** @var list<object> */
    private array $events = [];

    public function clear(): void
    {
        $this->events = [];
    }

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

final class InMemorySession implements SessionInterface
{
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
        return 'in-memory-session';
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
    public function setId(string $id): void
    {
    }
}
