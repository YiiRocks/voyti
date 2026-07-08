<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Repository;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
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

    public function testSearchWithStringLimitIsCastToInteger(): void
    {
        $repository = new UserRepository();

        for ($i = 0; $i < 3; $i++) {
            $this->createUser('user' . $i, 'user' . $i . '@example.com', time() + $i);
        }

        $result = $repository->search(['limit' => '2', 'page' => 1]);
        self::assertCount(2, $result);
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
