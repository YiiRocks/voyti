<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserPasswordHistory;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\Password\ResetService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Security\PasswordHasher;

#[AllowMockObjectsWithoutExpectations]
final class ResetServiceTest extends TestCase
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

    public function testRunDeletesProvidedToken(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');

        $user = $this->createUser(username: 'tokenuser', email: 'token@example.com', passwordHash: 'oldhash');
        $userToken = $this->createUserToken((int) $user->getId(), 'tokencode');

        $this->createService($eventDispatcher)->run('newpassword', $user, $userToken);

        self::assertNull(UserToken::findByCodeAndType('tokencode', UserToken::TYPE_RECOVERY));
    }

    public function testRunRecordsPasswordHistoryWhenEnabled(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true);
        $user = $this->createUser(username: 'historyuser', email: 'history@example.com', passwordHash: 'oldhash');

        $this->createService(config: $config)->run('newpassword', $user, null);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        $history = UserPasswordHistory::findByUserId($reloaded->getIdOrZero());
        self::assertCount(1, $history);
        self::assertTrue((new PasswordHasher())->validate('newpassword', $history[0]->getPasswordHash()));
    }

    public function testRunRejectsRecentlyUsedPassword(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true);
        $user = $this->createUser(username: 'reuseuser', email: 'reuse@example.com', passwordHash: 'oldhash');

        $this->createService(config: $config)->run('newpassword', $user, null);
        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);

        $result = $this->createService(config: $config)->run('newpassword', $reloaded, null);

        self::assertFalse($result);
    }

    public function testRunSetsPasswordChangedAt(): void
    {
        $user = $this->createUser(username: 'changeduser', email: 'changed@example.com', passwordHash: 'oldhash', createdAt: time() - 1000);

        $this->createService()->run('newpassword', $user, null);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getPasswordChangedAt());
        self::assertGreaterThan(time() - 100, $reloaded->getPasswordChangedAt());
    }

    public function testRunSetsPasswordHash(): void
    {
        $user = $this->createUser(username: 'hashuser', email: 'hash@example.com', passwordHash: 'oldhash');

        $this->createService()->run('newpassword', $user, null);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertNotSame('oldhash', $reloaded->getPasswordHash());
        self::assertTrue(password_verify('newpassword', $reloaded->getPasswordHash()));
    }

    public function testRunSetsUpdatedAt(): void
    {
        $user = $this->createUser(username: 'updateduser', email: 'updated@example.com', passwordHash: 'oldhash', createdAt: time() - 1000);

        $this->createService()->run('newpassword', $user, null);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getUpdatedAt());
        self::assertGreaterThan(time() - 100, $reloaded->getUpdatedAt());
    }

    public function testRunWithoutUserToken(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();

        $user = $this->createUser(username: 'testuser', email: 'test@example.com', passwordHash: 'oldhash');

        $result = $this->createService($eventDispatcher)->run('newpassword', $user, null);
        self::assertTrue($result);
        self::assertCount(1, $eventDispatcher->getEvents());
        $event = $eventDispatcher->getEvent(UserEvent::class);
        self::assertNotNull($event);
        self::assertSame(UserEvent::PASSWORD_RESET, $event->getType());
    }

    public function testRunWithUserToken(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');

        $user = $this->createUser(username: 'testuser', email: 'test@example.com', passwordHash: 'oldhash');
        $userToken = $this->createUserToken((int) $user->getId(), 'tokencode');

        $result = $this->createService($eventDispatcher)->run('newpassword', $user, $userToken);
        self::assertTrue($result);
    }

    private function createService(?EventDispatcherInterface $eventDispatcher = null, ?ModuleConfig $config = null): ResetService
    {
        $eventDispatcher ??= $this->createMock(EventDispatcherInterface::class);
        $config ??= new ModuleConfig();
        $passwordHasher = new PasswordHasher();

        return new ResetService($config, $eventDispatcher, new PasswordHistoryService($passwordHasher, $config));
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
