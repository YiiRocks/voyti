<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Repository;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Repository\BaseRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserRepositoryTest extends TestCase
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
        $db->createCommand('CREATE TABLE {{%user_profile}} (
            user_id INTEGER NOT NULL PRIMARY KEY,
            name VARCHAR(255),
            public_email VARCHAR(255),
            gravatar_email VARCHAR(255),
            location VARCHAR(255),
            website VARCHAR(255),
            bio TEXT,
            timezone VARCHAR(40)
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
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user_profile}}')->execute();
            $this->getDb()->createCommand('DROP TABLE IF EXISTS {{%user}}')->execute();
            ConnectionProvider::clear();
        }

        parent::tearDown();
    }

    public function testCountByFiltersAppliesEmailFilter(): void
    {
        $repository = new UserRepository();
        $this->insertUser('alice', 'alice@example.com');
        $this->insertUser('bob', 'bob@example.com');

        self::assertSame(2, $repository->countByFilters([]));
        self::assertSame(1, $repository->countByFilters(['email' => 'bob@']));
    }

    public function testCountByFiltersAppliesUsernameFilter(): void
    {
        $repository = new UserRepository();
        $this->insertUser('alice', 'alice@example.com');
        $this->insertUser('bob', 'bob@example.com');

        self::assertSame(2, $repository->countByFilters([]));
        self::assertSame(1, $repository->countByFilters(['username' => 'ali']));
    }

    public function testCountByFiltersCombinesUsernameAndEmailFilters(): void
    {
        $repository = new UserRepository();
        $this->insertUser('alice', 'alice@example.com');
        $this->insertUser('bob', 'bob@example.com');

        self::assertSame(1, $repository->countByFilters(['username' => 'alice', 'email' => 'alice@']));
        self::assertSame(0, $repository->countByFilters(['username' => 'alice', 'email' => 'bob@']));
    }

    public function testFindAllHonoursNonEmptyCondition(): void
    {
        $this->insertUser('alice', 'alice@example.com');
        $this->insertUser('bob', 'bob@example.com');

        $repository = new TestableUserRepository();

        $all = $repository->callFindAll(User::class, []);
        self::assertCount(2, $all);

        $filtered = $repository->callFindAll(User::class, ['username' => 'bob']);
        self::assertCount(1, $filtered);
        self::assertSame('bob', $filtered[0]->getUsername());
    }

    public function testFindAllUsersReturnsEveryRowWhenMoreThanOne(): void
    {
        $repository = new UserRepository();
        $this->insertUser('alice', 'alice@example.com');
        $this->insertUser('bob', 'bob@example.com');
        $this->insertUser('carol', 'carol@example.com');

        $users = $repository->findAllUsers();

        $usernames = array_map(static fn (User $user): string => $user->getUsername(), $users);
        self::assertSame(['alice', 'bob', 'carol'], $usernames);
    }

    public function testFindByUsernameOnlyMatchesUsername(): void
    {
        $repository = new UserRepository();
        $this->insertUser('alice', 'alice@example.com');
        $this->insertUser('bob', 'bob@example.com');

        $found = $repository->findByUsername('bob');

        self::assertSame('bob', $found->getUsername());
        self::assertSame('bob@example.com', $found->getEmail());
    }

    public function testFindByUsernameOrEmailMatchesByEmail(): void
    {
        $repository = new UserRepository();
        $this->insertUser('alice', 'alice@example.com');

        $found = $repository->findByUsernameOrEmail('alice@example.com');

        self::assertSame('alice', $found->getUsername());
    }

    public function testFindByUsernameOrEmailMatchesByUsername(): void
    {
        $repository = new UserRepository();
        $this->insertUser('alice', 'other@example.com');
        $this->insertUser('bob', 'bob@example.com');

        $found = $repository->findByUsernameOrEmail('alice');

        self::assertSame('alice', $found->getUsername());
    }

    public function testFindByUsernameReturnsNullWhenNoMatch(): void
    {
        $repository = new UserRepository();
        $this->insertUser('alice', 'alice@example.com');

        self::assertNull($repository->findByUsername('nobody'));
    }

    public function testSaveIsCallablePubliclyAndPersistsModel(): void
    {
        $repository = new UserRepository();
        $user = $this->createUser('dave', 'dave@example.com');

        $repository->save($user);

        self::assertNotNull($user->getId());
        $reloaded = User::query()->findByPk((int) $user->getId());
        self::assertSame('dave', $reloaded->getUsername());
    }

    public function testSaveWithProfileAndTokenAssignsRealUserIdWhenAvailable(): void
    {
        $repository = new UserRepository();
        $user = $this->createUser('grace', 'grace@example.com');
        $profile = new UserProfile();
        $token = new UserToken();
        $token->setCode(str_repeat('a', 32));
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());

        $repository->saveWithProfileAndToken($user, $profile, $token);

        self::assertNotNull($user->getId());
        self::assertSame((int) $user->getId(), $profile->getUserId());
        self::assertSame((int) $user->getId(), $token->getUserId());
    }

    public function testSaveWithProfileAndTokenAssignsZeroUserIdToProfileAndTokenWhenUserIdUnavailable(): void
    {
        $repository = new UserRepository();
        $user = $this->createUser('heidi', 'heidi@example.com');
        // Mark the (unpersisted) user as existing so save() performs a no-op update instead of an
        // insert, leaving getId() null and forcing the "user id unavailable" branch.
        $user->markAsExisting();
        $profile = new UserProfile();
        $token = new UserToken();
        $token->setCode(str_repeat('b', 32));
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());

        $repository->saveWithProfileAndToken($user, $profile, $token);

        self::assertNull($user->getId());
        self::assertSame(0, $profile->getUserId());
        self::assertSame(0, $token->getUserId());
    }

    public function testSaveWithProfileAssignsRealUserIdWhenAvailable(): void
    {
        $repository = new UserRepository();
        $user = $this->createUser('erin', 'erin@example.com');
        $profile = new UserProfile();

        $repository->saveWithProfile($user, $profile);

        self::assertNotNull($user->getId());
        self::assertSame((int) $user->getId(), $profile->getUserId());
    }

    public function testSaveWithProfileAssignsZeroUserIdWhenUserIdUnavailable(): void
    {
        $repository = new UserRepository();
        $user = $this->createUser('frank', 'frank@example.com');
        // Mark the (unpersisted) user as existing so save() performs a no-op update instead of an
        // insert, leaving getId() null and forcing the "user id unavailable" branch.
        $user->markAsExisting();
        $profile = new UserProfile();

        $repository->saveWithProfile($user, $profile);

        self::assertNull($user->getId());
        self::assertSame(0, $profile->getUserId());
    }

    public function testSearchAppliesEmailFilter(): void
    {
        $repository = new UserRepository();
        $this->insertUser('alice', 'alice@example.com');
        $this->insertUser('bob', 'bob@example.com');

        $results = $repository->search(['email' => 'bob@']);

        self::assertCount(1, $results);
        self::assertSame('bob', $results[0]->getUsername());
    }

    public function testSearchAppliesLimitAndPageOffset(): void
    {
        $repository = new UserRepository();
        foreach (['alice', 'bob', 'carol', 'dave'] as $name) {
            $this->insertUser($name, "{$name}@example.com");
        }

        $results = $repository->search(['limit' => 2, 'page' => 2]);

        self::assertCount(2, $results);
        self::assertSame('carol', $results[0]->getUsername());
        self::assertSame('dave', $results[1]->getUsername());
    }

    public function testSearchAppliesStatusFilter(): void
    {
        $repository = new UserRepository();
        $blocked = $this->insertUser('blockeduser', 'blocked@example.com');
        $blocked->setBlockedAt(time());
        $blocked->setConfirmedAt(time());
        $blocked->save();
        $confirmed = $this->insertUser('confirmeduser', 'confirmed@example.com');
        $confirmed->setConfirmedAt(time());
        $confirmed->save();
        $this->insertUser('unconfirmeduser', 'unconfirmed@example.com');

        $blockedResults = $repository->search(['status' => 'blocked']);
        self::assertCount(1, $blockedResults);
        self::assertSame('blockeduser', $blockedResults[0]->getUsername());

        $confirmedResults = $repository->search(['status' => 'confirmed']);
        self::assertCount(2, $confirmedResults);
        self::assertSame('blockeduser', $confirmedResults[0]->getUsername());
        self::assertSame('confirmeduser', $confirmedResults[1]->getUsername());

        $unconfirmedResults = $repository->search(['status' => 'unconfirmed']);
        self::assertCount(1, $unconfirmedResults);
        self::assertSame('unconfirmeduser', $unconfirmedResults[0]->getUsername());
    }

    public function testSearchAppliesUsernameFilter(): void
    {
        $repository = new UserRepository();
        $this->insertUser('alice', 'alice@example.com');
        $this->insertUser('bob', 'bob@example.com');

        $results = $repository->search(['username' => 'ali']);

        self::assertCount(1, $results);
        self::assertSame('alice', $results[0]->getUsername());
    }

    public function testSearchCastsStringLimitFilterToInt(): void
    {
        $repository = new UserRepository();
        foreach (['alice', 'bob', 'carol'] as $name) {
            $this->insertUser($name, "{$name}@example.com");
        }

        $results = $repository->search(['limit' => '2']);

        self::assertCount(2, $results);
    }

    public function testSearchClampsNonPositivePageToFirstPage(): void
    {
        $repository = new UserRepository();
        foreach (['alice', 'bob'] as $name) {
            $this->insertUser($name, "{$name}@example.com");
        }

        $results = $repository->search(['page' => 0, 'limit' => 1]);

        self::assertCount(1, $results);
        self::assertSame('alice', $results[0]->getUsername());
    }

    public function testSearchDefaultLimitIsFifty(): void
    {
        $repository = new UserRepository();
        for ($i = 1; $i <= 51; $i++) {
            $this->insertUser("user{$i}", "user{$i}@example.com");
        }

        $results = $repository->search([]);

        self::assertCount(50, $results);
        self::assertSame('user1', $results[0]->getUsername());
        self::assertSame('user50', $results[49]->getUsername());
    }

    private function createUser(string $username, string $email): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setUpdatedAt(time());
        $user->setCreatedAt(time());
        return $user;
    }

    private function insertUser(string $username, string $email): User
    {
        $user = $this->createUser($username, $email);
        $user->save();
        return $user;
    }
}

/**
 * @extends BaseRepository<User>
 */
final class TestableUserRepository extends BaseRepository
{
    /**
     * @return list<User>
     */
    public function callFindAll(string $class, array $condition = []): array
    {
        /** @var list<User> */
        return $this->findAll($class, $condition);
    }
}
