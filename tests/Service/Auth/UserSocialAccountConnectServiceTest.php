<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Auth;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSocialAccount;
use YiiRocks\Voyti\Service\Auth\UserSocialAccountConnectService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

final class UserSocialAccountConnectServiceTest extends TestCase
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

    public function testRunExistingConnectedAccountReturnsFailure(): void
    {
        $user = new User();
        $user->setUsername('test');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('client123');
        $account->setData('{}');
        $account->setUserId((int) $user->getId());
        $account->setCreatedAt(time());
        $account->save();

        $service = new UserSocialAccountConnectService();

        $result = $service->run('github', 'client123', ['email' => 'test@example.com'], 42);

        self::assertTrue($result->isFailure());
        self::assertSame('This account has already been connected to another user', $result->getMessage());
    }

    public function testRunExistingUnconnectedAccountUpdatesAndConnects(): void
    {
        $user = new User();
        $user->setUsername('target');
        $user->setEmail('target@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('existing_unconnected');
        $account->setCode('old_code');
        $account->setUsername('olduser');
        $account->setEmail('old@example.com');
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        $service = new UserSocialAccountConnectService();

        $result = $service->run('github', 'existing_unconnected', ['email' => 'new@example.com'], (int) $user->getId());

        self::assertTrue($result->isSuccess());

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'existing_unconnected');
        self::assertNotNull($saved);
        self::assertSame((int) $user->getId(), $saved->getUserId());
        self::assertNull($saved->getCode());
        self::assertNull($saved->getUsername());
        self::assertNull($saved->getEmail());
    }

    public function testRunNewAccountCreatesAndConnects(): void
    {
        $service = new UserSocialAccountConnectService();

        $attributes = ['email' => 'new@example.com'];
        $result = $service->run('github', 'new_client', $attributes, 100);

        self::assertTrue($result->isSuccess());

        $saved = UserSocialAccount::findByProviderAndClientId('github', 'new_client');
        self::assertNotNull($saved);
        self::assertSame(100, $saved->getUserId());
        self::assertSame(json_encode($attributes, JSON_THROW_ON_ERROR), $saved->getData());
        self::assertGreaterThan(0, $saved->getCreatedAt());
        self::assertNull($saved->getEmail());
        self::assertNull($saved->getUsername());
        self::assertNull($saved->getCode());
    }
}
