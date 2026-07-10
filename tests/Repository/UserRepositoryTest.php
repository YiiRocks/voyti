<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Repository;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserProfile;
use YiiRocks\Voyti\Repository\UserProfileRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

final class UserRepositoryTest extends TestCase
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

    public function testCountByFiltersWithEmailFilter(): void
    {
        $repository = new UserRepository();
        $this->createUser('alice', 'alice@example.com', time());
        $this->createUser('bob', 'bob@other.com', time());

        self::assertSame(1, $repository->countByFilters(['email' => 'example.com']));
    }

    public function testCountByFiltersWithNoFilters(): void
    {
        $repository = new UserRepository();
        $this->createUser('alice', 'alice@example.com', time());
        $this->createUser('bob', 'bob@example.com', time());

        self::assertSame(2, $repository->countByFilters());
    }

    public function testCountByFiltersWithUsernameFilter(): void
    {
        $repository = new UserRepository();
        $this->createUser('alice', 'alice@example.com', time());
        $this->createUser('bob', 'bob@example.com', time());

        self::assertSame(1, $repository->countByFilters(['username' => 'ali']));
    }

    public function testDeleteRemovesUserAndProfile(): void
    {
        $repository = new UserRepository();
        $profileRepository = new UserProfileRepository();
        $user = $this->createUser('alice', 'alice@example.com', time());

        $profile = new UserProfile();
        $profile->setUserId((int) $user->getId());
        $profile->setName('Alice');
        $profile->save();

        $repository->delete($user);

        self::assertNull($repository->findByUsername('alice'));
        self::assertNull($profileRepository->findByUserId((int) $user->getId()));
    }

    public function testDeleteWithoutProfileOnlyRemovesUser(): void
    {
        $repository = new UserRepository();
        $user = $this->createUser('alice', 'alice@example.com', time());

        $repository->delete($user);

        self::assertNull($repository->findByUsername('alice'));
    }

    public function testFindAllUsersReturnsAllUsers(): void
    {
        $repository = new UserRepository();
        $this->createUser('alice', 'alice@example.com', time());
        $this->createUser('bob', 'bob@example.com', time());

        self::assertCount(2, $repository->findAllUsers());
    }

    public function testFindByIdsReturnsMatchingUsers(): void
    {
        $repository = new UserRepository();
        $alice = $this->createUser('alice', 'alice@example.com', time());
        $bob = $this->createUser('bob', 'bob@example.com', time());
        $this->createUser('carol', 'carol@example.com', time());

        $result = $repository->findByIds([(int) $alice->getId(), (int) $bob->getId()]);

        self::assertCount(2, $result);
    }

    public function testFindByUsernameOrEmailMatchesByEmail(): void
    {
        $repository = new UserRepository();
        $this->createUser('alice', 'alice@example.com', time());

        $user = $repository->findByUsernameOrEmail('alice@example.com');

        self::assertNotNull($user);
        self::assertSame('alice', $user->getUsername());
    }

    public function testFindByUsernameOrEmailMatchesByUsername(): void
    {
        $repository = new UserRepository();
        $this->createUser('alice', 'alice@example.com', time());

        $user = $repository->findByUsernameOrEmail('alice');

        self::assertNotNull($user);
        self::assertSame('alice@example.com', $user->getEmail());
    }

    public function testSearchClampsPageZeroToFirstPage(): void
    {
        $repository = new UserRepository();

        for ($i = 0; $i < 3; $i++) {
            $this->createUser('user' . $i, 'user' . $i . '@example.com', time() + $i);
        }

        $pageZero = $repository->search(['page' => 0, 'limit' => 2]);
        $pageOne = $repository->search(['page' => 1, 'limit' => 2]);

        self::assertCount(2, $pageZero);

        $idsZero = array_map(static fn (User $u): int => (int) $u->getId(), $pageZero);
        $idsOne = array_map(static fn (User $u): int => (int) $u->getId(), $pageOne);
        self::assertSame($idsOne, $idsZero);
    }

    public function testSearchDefaultLimitIsFifty(): void
    {
        $repository = new UserRepository();

        for ($i = 0; $i < 51; $i++) {
            $this->createUser('user' . $i, 'user' . $i . '@example.com', time() + $i);
        }

        $result = $repository->search([]);
        self::assertCount(50, $result);
    }

    public function testSearchReturnsSecondPage(): void
    {
        $repository = new UserRepository();

        for ($i = 0; $i < 3; $i++) {
            $this->createUser('user' . $i, 'user' . $i . '@example.com', time() + $i);
        }

        $firstPage = $repository->search(['page' => 1, 'limit' => 2]);
        $secondPage = $repository->search(['page' => 2, 'limit' => 2]);

        self::assertCount(2, $firstPage);
        self::assertCount(1, $secondPage);
        self::assertNotSame($firstPage[0]->getEmail(), $secondPage[0]->getEmail());
    }

    public function testSearchWithBlockedStatusFilter(): void
    {
        $repository = new UserRepository();
        $blocked = $this->createUser('alice', 'alice@example.com', time());
        $blocked->setBlockedAt(time());
        $blocked->save();
        $this->createUser('bob', 'bob@example.com', time());

        $result = $repository->search(['status' => 'blocked']);

        self::assertCount(1, $result);
        self::assertSame('alice', $result[0]->getUsername());
    }

    public function testSearchWithConfirmedStatusFilter(): void
    {
        $repository = new UserRepository();
        $confirmed = $this->createUser('alice', 'alice@example.com', time());
        $confirmed->setConfirmedAt(time());
        $confirmed->save();
        $this->createUser('bob', 'bob@example.com', time());

        $result = $repository->search(['status' => 'confirmed']);

        self::assertCount(1, $result);
        self::assertSame('alice', $result[0]->getUsername());
    }

    public function testSearchWithEmailFilter(): void
    {
        $repository = new UserRepository();
        $this->createUser('alice', 'alice@example.com', time());
        $this->createUser('bob', 'bob@other.com', time());

        $result = $repository->search(['email' => 'example.com']);

        self::assertCount(1, $result);
        self::assertSame('alice', $result[0]->getUsername());
    }

    public function testSearchWithStringLimitIsCastToInteger(): void
    {
        $repository = new UserRepository();

        for ($i = 0; $i < 3; $i++) {
            $this->createUser('user' . $i, 'user' . $i . '@example.com', time() + $i);
        }

        $result = $repository->search(['limit' => '2', 'page' => 1]);
        self::assertCount(2, $result);
    }

    public function testSearchWithUnconfirmedStatusFilter(): void
    {
        $repository = new UserRepository();
        $confirmed = $this->createUser('alice', 'alice@example.com', time());
        $confirmed->setConfirmedAt(time());
        $confirmed->save();
        $this->createUser('bob', 'bob@example.com', time());

        $result = $repository->search(['status' => 'unconfirmed']);

        self::assertCount(1, $result);
        self::assertSame('bob', $result[0]->getUsername());
    }

    public function testSearchWithUsernameFilter(): void
    {
        $repository = new UserRepository();
        $this->createUser('alice', 'alice@example.com', time());
        $this->createUser('bob', 'bob@example.com', time());

        $result = $repository->search(['username' => 'ali']);

        self::assertCount(1, $result);
        self::assertSame('alice', $result[0]->getUsername());
    }

    private function createUser(string $username, string $email, int $createdAt): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt($createdAt);
        $user->setUpdatedAt($createdAt);
        $user->save();

        return $user;
    }
}
