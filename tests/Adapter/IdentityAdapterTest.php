<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Adapter;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use YiiRocks\Voyti\Adapter\IdentityAdapter;
use YiiRocks\Voyti\Clock\SystemClock;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FixedClock;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;

final class IdentityAdapterTest extends TestCase
{
    use DatabaseSetupTrait;
    use UserFactoryTrait;

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
        $user = $this->createUser();
        $this->createApiToken($user, 'raw-token', createdAt: $now - 500);
        $adapter = $this->createAdapter(config: ModuleConfigFactory::create(apiTokenLifespan: 500), clock: $this->fixedClock($now));

        self::assertNotNull($adapter->findIdentityByToken('raw-token'));
    }

    public function testFindIdentityByTokenHashesInputBeforeLookup(): void
    {
        $user = $this->createUser();
        $this->createApiToken($user, 'raw-token');
        $adapter = $this->createAdapter();

        self::assertNotNull($adapter->findIdentityByToken('raw-token'));
        self::assertNull($adapter->findIdentityByToken(hash('sha256', 'raw-token')));
    }

    public function testFindIdentityByTokenReturnsNullForExpiredToken(): void
    {
        $user = $this->createUser();
        $this->createApiToken($user, 'raw-token', createdAt: time() - 1000);
        $adapter = $this->createAdapter(config: ModuleConfigFactory::create(apiTokenLifespan: 500));

        self::assertNull($adapter->findIdentityByToken('raw-token'));
    }

    public function testFindIdentityByTokenReturnsNullForUnknownToken(): void
    {
        $adapter = $this->createAdapter();

        self::assertNull($adapter->findIdentityByToken('does-not-exist'));
    }

    public function testFindIdentityByTokenReturnsUserForValidToken(): void
    {
        $user = $this->createUser();
        $this->createApiToken($user, 'raw-token');
        $adapter = $this->createAdapter();

        $identity = $adapter->findIdentityByToken('raw-token');

        self::assertInstanceOf(User::class, $identity);
        self::assertSame($user->getId(), $identity->getId());
    }

    public function testFindIdentityByTokenUsesInjectedClockNotRealTime(): void
    {
        $user = $this->createUser();
        $this->createApiToken($user, 'raw-token', createdAt: 1_000_000);
        $adapter = $this->createAdapter(
            config: ModuleConfigFactory::create(apiTokenLifespan: 500),
            clock: $this->fixedClock(1_000_100),
        );

        self::assertNotNull($adapter->findIdentityByToken('raw-token'));
    }

    public function testFindIdentityByTokenWithinLifespanIsValid(): void
    {
        $user = $this->createUser();
        $this->createApiToken($user, 'raw-token', createdAt: time() - 100);
        $adapter = $this->createAdapter(config: ModuleConfigFactory::create(apiTokenLifespan: 500));

        self::assertNotNull($adapter->findIdentityByToken('raw-token'));
    }

    public function testFindIdentityByTokenWithNullLifespanNeverExpires(): void
    {
        $user = $this->createUser();
        $this->createApiToken($user, 'raw-token', createdAt: time() - 1_000_000);
        $adapter = $this->createAdapter(config: ModuleConfigFactory::create(apiTokenLifespan: null));

        self::assertNotNull($adapter->findIdentityByToken('raw-token'));
    }

    public function testFindIdentityDelegatesToUserRepository(): void
    {
        $user = $this->createUser();
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

    private function createAdapter(?ModuleConfig $config = null, ?ClockInterface $clock = null): IdentityAdapter
    {
        return new IdentityAdapter($config ?? ModuleConfigFactory::create(), $clock ?? new SystemClock());
    }

    private function createApiToken(User $user, string $rawToken, ?int $createdAt = null): UserToken
    {
        $userToken = new UserToken();
        $userToken->setUserId((int) $user->getId());
        $userToken->setType(UserToken::TYPE_API_ACCESS);
        $userToken->setCode(hash('sha256', $rawToken));
        $userToken->setCreatedAt($createdAt ?? time());
        $userToken->save();
        return $userToken;
    }

    private function fixedClock(int $timestamp): ClockInterface
    {
        return new FixedClock((new DateTimeImmutable())->setTimestamp($timestamp));
    }
}
