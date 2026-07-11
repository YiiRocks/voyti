<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\ResetService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use Yiisoft\Security\PasswordHasher;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ResetServiceTest extends TestCase
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

    public function testRunDeletesProvidedToken(): void
    {
        $passwordHasher = new PasswordHasher();
        $config = new ModuleConfig();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(3))->method('dispatch');

        $service = new ResetService($passwordHasher, $config, $eventDispatcher);

        $user = new User();
        $user->setUsername('tokenuser');
        $user->setEmail('token@example.com');
        $user->setPasswordHash('oldhash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $userToken = new UserToken();
        $userToken->setUserId((int) $user->getId());
        $userToken->setCode('tokencode');
        $userToken->setType(UserToken::TYPE_RECOVERY);
        $userToken->setCreatedAt(time());
        $userToken->save();

        $service->run('newpassword', $user, $userToken);

        self::assertNull(UserToken::findByCodeAndType('tokencode', UserToken::TYPE_RECOVERY));
    }

    public function testRunPersistsUser(): void
    {
        $passwordHasher = new PasswordHasher();
        $config = new ModuleConfig();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new ResetService($passwordHasher, $config, $eventDispatcher);

        $user = new User();
        $user->setUsername('persistuser');
        $user->setEmail('persist@example.com');
        $user->setPasswordHash('oldhash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $service->run('newpassword', $user, null);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertNotSame('oldhash', $reloaded->getPasswordHash());
    }

    public function testRunSetsPasswordChangedAt(): void
    {
        $passwordHasher = new PasswordHasher();
        $config = new ModuleConfig();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new ResetService($passwordHasher, $config, $eventDispatcher);

        $user = new User();
        $user->setUsername('changeduser');
        $user->setEmail('changed@example.com');
        $user->setPasswordHash('oldhash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time() - 1000);
        $user->setUpdatedAt(time() - 1000);
        $user->save();

        $service->run('newpassword', $user, null);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getPasswordChangedAt());
        self::assertGreaterThan(time() - 100, $reloaded->getPasswordChangedAt());
    }

    public function testRunSetsPasswordHash(): void
    {
        $passwordHasher = new PasswordHasher();
        $config = new ModuleConfig();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new ResetService($passwordHasher, $config, $eventDispatcher);

        $user = new User();
        $user->setUsername('hashuser');
        $user->setEmail('hash@example.com');
        $user->setPasswordHash('oldhash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $service->run('newpassword', $user, null);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertNotSame('oldhash', $reloaded->getPasswordHash());
        self::assertTrue(password_verify('newpassword', $reloaded->getPasswordHash()));
    }

    public function testRunSetsUpdatedAt(): void
    {
        $passwordHasher = new PasswordHasher();
        $config = new ModuleConfig();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new ResetService($passwordHasher, $config, $eventDispatcher);

        $user = new User();
        $user->setUsername('updateduser');
        $user->setEmail('updated@example.com');
        $user->setPasswordHash('oldhash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time() - 1000);
        $user->setUpdatedAt(time() - 1000);
        $user->save();

        $service->run('newpassword', $user, null);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getUpdatedAt());
        self::assertGreaterThan(time() - 100, $reloaded->getUpdatedAt());
    }

    public function testRunWithoutUserToken(): void
    {
        $passwordHasher = new PasswordHasher();
        $config = new ModuleConfig();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');

        $service = new ResetService($passwordHasher, $config, $eventDispatcher);

        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('oldhash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $result = $service->run('newpassword', $user, null);
        self::assertTrue($result);
    }

    public function testRunWithUserToken(): void
    {
        $passwordHasher = new PasswordHasher();
        $config = new ModuleConfig();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(3))->method('dispatch');

        $service = new ResetService($passwordHasher, $config, $eventDispatcher);

        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPasswordHash('oldhash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $userToken = new UserToken();
        $userToken->setUserId((int) $user->getId());
        $userToken->setCode('tokencode');
        $userToken->setType(UserToken::TYPE_RECOVERY);
        $userToken->setCreatedAt(time());
        $userToken->save();

        $result = $service->run('newpassword', $user, $userToken);
        self::assertTrue($result);
    }
}
