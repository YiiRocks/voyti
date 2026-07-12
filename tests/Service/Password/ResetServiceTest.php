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
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(3))->method('dispatch');

        $user = $this->createUser('tokenuser', 'token@example.com');
        $userToken = $this->createUserToken((int) $user->getId(), 'tokencode');

        $this->createService($eventDispatcher)->run('newpassword', $user, $userToken);

        self::assertNull(UserToken::findByCodeAndType('tokencode', UserToken::TYPE_RECOVERY));
    }

    public function testRunPersistsUser(): void
    {
        $user = $this->createUser('persistuser', 'persist@example.com');

        $this->createService()->run('newpassword', $user, null);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertNotSame('oldhash', $reloaded->getPasswordHash());
    }

    public function testRunSetsPasswordChangedAt(): void
    {
        $user = $this->createUser('changeduser', 'changed@example.com', time() - 1000);

        $this->createService()->run('newpassword', $user, null);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getPasswordChangedAt());
        self::assertGreaterThan(time() - 100, $reloaded->getPasswordChangedAt());
    }

    public function testRunSetsPasswordHash(): void
    {
        $user = $this->createUser('hashuser', 'hash@example.com');

        $this->createService()->run('newpassword', $user, null);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertNotSame('oldhash', $reloaded->getPasswordHash());
        self::assertTrue(password_verify('newpassword', $reloaded->getPasswordHash()));
    }

    public function testRunSetsUpdatedAt(): void
    {
        $user = $this->createUser('updateduser', 'updated@example.com', time() - 1000);

        $this->createService()->run('newpassword', $user, null);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getUpdatedAt());
        self::assertGreaterThan(time() - 100, $reloaded->getUpdatedAt());
    }

    public function testRunWithoutUserToken(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');

        $user = $this->createUser('testuser', 'test@example.com');

        $result = $this->createService($eventDispatcher)->run('newpassword', $user, null);
        self::assertTrue($result);
    }

    public function testRunWithUserToken(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(3))->method('dispatch');

        $user = $this->createUser('testuser', 'test@example.com');
        $userToken = $this->createUserToken((int) $user->getId(), 'tokencode');

        $result = $this->createService($eventDispatcher)->run('newpassword', $user, $userToken);
        self::assertTrue($result);
    }

    private function createService(?EventDispatcherInterface $eventDispatcher = null): ResetService
    {
        $eventDispatcher ??= $this->createMock(EventDispatcherInterface::class);

        return new ResetService(new PasswordHasher(), new ModuleConfig(), $eventDispatcher);
    }

    private function createUser(string $username, string $email, ?int $createdAt = null): User
    {
        $createdAt ??= time();

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('oldhash');
        $user->setAuthKey('key');
        $user->setCreatedAt($createdAt);
        $user->setUpdatedAt($createdAt);
        $user->save();

        return $user;
    }

    private function createUserToken(int $userId, string $code): UserToken
    {
        $userToken = new UserToken();
        $userToken->setUserId($userId);
        $userToken->setCode($code);
        $userToken->setType(UserToken::TYPE_RECOVERY);
        $userToken->setCreatedAt(time());
        $userToken->save();

        return $userToken;
    }
}
