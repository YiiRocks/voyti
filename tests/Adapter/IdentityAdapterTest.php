<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Adapter;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Adapter\IdentityAdapter;
use YiiRocks\Voyti\Helper\ApiTokenHasher;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

final class IdentityAdapterTest extends TestCase
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
        $now = time();
        $user = $this->createSavedUser();
        $this->createApiToken($user, 'raw-token', createdAt: $now - 500);
        $adapter = $this->createAdapter(config: new ModuleConfig(apiTokenLifespan: 500), now: static fn (): int => $now);

        self::assertNotNull($adapter->findIdentityByToken('raw-token'));
    }

    public function testFindIdentityByTokenHashesInputBeforeLookup(): void
    {
        $user = $this->createSavedUser();
        $this->createApiToken($user, 'raw-token');
        $adapter = $this->createAdapter();

        self::assertNotNull($adapter->findIdentityByToken('raw-token'));
        self::assertNull($adapter->findIdentityByToken(ApiTokenHasher::hash('raw-token')));
    }

    public function testFindIdentityByTokenReturnsNullForExpiredToken(): void
    {
        $user = $this->createSavedUser();
        $this->createApiToken($user, 'raw-token', createdAt: time() - 1000);
        $adapter = $this->createAdapter(config: new ModuleConfig(apiTokenLifespan: 500));

        self::assertNull($adapter->findIdentityByToken('raw-token'));
    }

    public function testFindIdentityByTokenReturnsNullForUnknownToken(): void
    {
        $adapter = $this->createAdapter();

        self::assertNull($adapter->findIdentityByToken('does-not-exist'));
    }

    public function testFindIdentityByTokenReturnsUserForValidToken(): void
    {
        $user = $this->createSavedUser();
        $this->createApiToken($user, 'raw-token');
        $adapter = $this->createAdapter();

        $identity = $adapter->findIdentityByToken('raw-token');

        self::assertInstanceOf(User::class, $identity);
        self::assertSame($user->getId(), $identity->getId());
    }

    public function testFindIdentityByTokenUsesInjectedNowClosureNotRealTime(): void
    {
        $user = $this->createSavedUser();
        $this->createApiToken($user, 'raw-token', createdAt: 1_000_000);
        $adapter = $this->createAdapter(
            config: new ModuleConfig(apiTokenLifespan: 500),
            now: static fn (): int => 1_000_100,
        );

        self::assertNotNull($adapter->findIdentityByToken('raw-token'));
    }

    public function testFindIdentityByTokenWithinLifespanIsValid(): void
    {
        $user = $this->createSavedUser();
        $this->createApiToken($user, 'raw-token', createdAt: time() - 100);
        $adapter = $this->createAdapter(config: new ModuleConfig(apiTokenLifespan: 500));

        self::assertNotNull($adapter->findIdentityByToken('raw-token'));
    }

    public function testFindIdentityByTokenWithNullLifespanNeverExpires(): void
    {
        $user = $this->createSavedUser();
        $this->createApiToken($user, 'raw-token', createdAt: time() - 1_000_000);
        $adapter = $this->createAdapter(config: new ModuleConfig(apiTokenLifespan: null));

        self::assertNotNull($adapter->findIdentityByToken('raw-token'));
    }

    public function testFindIdentityDelegatesToUserRepository(): void
    {
        $user = $this->createSavedUser();
        $adapter = $this->createAdapter();

        $found = $adapter->findIdentity((string) $user->getId());

        self::assertInstanceOf(User::class, $found);
        self::assertSame($user->getId(), $found->getId());
    }

    public function testFindIdentityReturnsNullForUnknownId(): void
    {
        $adapter = $this->createAdapter();

        self::assertNull($adapter->findIdentity('999999'));
    }

    private function createAdapter(?ModuleConfig $config = null, ?\Closure $now = null): IdentityAdapter
    {
        return new IdentityAdapter($config ?? new ModuleConfig(), $now);
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
