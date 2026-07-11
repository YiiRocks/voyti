<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Repository;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Helper\ApiTokenHasher;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\IdentityRepository;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

final class IdentityRepositoryTest extends TestCase
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

    public function testFindIdentityByTokenAtExactLifespanBoundaryIsStillValid(): void
    {
        $user = $this->createSavedUser();
        $this->createApiToken($user, 'raw-token', createdAt: time() - 500);
        $repository = $this->createRepository(config: new ModuleConfig(apiTokenLifespan: 500));

        self::assertNotNull($repository->findIdentityByToken('raw-token'));
    }

    public function testFindIdentityByTokenHashesInputBeforeLookup(): void
    {
        $user = $this->createSavedUser();
        $this->createApiToken($user, 'raw-token');
        $repository = $this->createRepository();

        self::assertNotNull($repository->findIdentityByToken('raw-token'));
        self::assertNull($repository->findIdentityByToken(ApiTokenHasher::hash('raw-token')));
    }

    public function testFindIdentityByTokenReturnsNullForExpiredToken(): void
    {
        $user = $this->createSavedUser();
        $this->createApiToken($user, 'raw-token', createdAt: time() - 1000);
        $repository = $this->createRepository(config: new ModuleConfig(apiTokenLifespan: 500));

        self::assertNull($repository->findIdentityByToken('raw-token'));
    }

    public function testFindIdentityByTokenReturnsNullForUnknownToken(): void
    {
        $repository = $this->createRepository();

        self::assertNull($repository->findIdentityByToken('does-not-exist'));
    }

    public function testFindIdentityByTokenReturnsUserForValidToken(): void
    {
        $user = $this->createSavedUser();
        $this->createApiToken($user, 'raw-token');
        $repository = $this->createRepository();

        $identity = $repository->findIdentityByToken('raw-token');

        self::assertInstanceOf(User::class, $identity);
        self::assertSame($user->getId(), $identity->getId());
    }

    public function testFindIdentityByTokenWithinLifespanIsValid(): void
    {
        $user = $this->createSavedUser();
        $this->createApiToken($user, 'raw-token', createdAt: time() - 100);
        $repository = $this->createRepository(config: new ModuleConfig(apiTokenLifespan: 500));

        self::assertNotNull($repository->findIdentityByToken('raw-token'));
    }

    public function testFindIdentityByTokenWithNullLifespanNeverExpires(): void
    {
        $user = $this->createSavedUser();
        $this->createApiToken($user, 'raw-token', createdAt: time() - 1_000_000);
        $repository = $this->createRepository(config: new ModuleConfig(apiTokenLifespan: null));

        self::assertNotNull($repository->findIdentityByToken('raw-token'));
    }

    public function testFindIdentityDelegatesToUserRepository(): void
    {
        $user = $this->createSavedUser();
        $repository = $this->createRepository();

        $found = $repository->findIdentity((string) $user->getId());

        self::assertInstanceOf(User::class, $found);
        self::assertSame($user->getId(), $found->getId());
    }

    public function testFindIdentityReturnsNullForUnknownId(): void
    {
        $repository = $this->createRepository();

        self::assertNull($repository->findIdentity('999999'));
    }

    private function createApiToken(User $user, string $rawToken, ?int $createdAt = null): UserToken
    {
        $userToken = new UserToken();
        $userToken->setUserId((int) $user->getId());
        $userToken->setType(UserToken::TYPE_API_ACCESS);
        $userToken->setCode(ApiTokenHasher::hash($rawToken));
        $userToken->setCreatedAt($createdAt ?? time());
        $userToken->save();
        return $userToken;
    }

    private function createRepository(?ModuleConfig $config = null): IdentityRepository
    {
        return new IdentityRepository($config ?? new ModuleConfig());
    }

    private function createSavedUser(): User
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();
        return $user;
    }
}
