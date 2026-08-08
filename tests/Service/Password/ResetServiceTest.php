<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserPasswordHistory;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\Password\ResetService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;

#[AllowMockObjectsWithoutExpectations]
final class ResetServiceTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public function testRunDeletesProvidedToken(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');

        $user = $this->createUser(username: 'tokenuser', email: 'token@example.com', passwordHash: 'oldhash');
        $userId = (int) $user->getId();
        $userToken = $this->createUserToken($userId, 'tokencode');

        $this->createService($eventDispatcher)->run('newpassword', $user, $userToken);

        self::assertCount(0, UserToken::findByUserId($userId));
    }

    public function testRunRecordsPasswordHistoryWhenEnabled(): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);
        $user = $this->createUser(username: 'historyuser', email: 'history@example.com', passwordHash: 'oldhash');

        $this->createService(config: $config)->run('newpassword', $user, null);

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        $history = UserPasswordHistory::findByUserId($reloaded->getIdOrZero());
        self::assertCount(1, $history);
        self::assertTrue((TestPasswordHasherFactory::create())->validate('newpassword', $history[0]->getPasswordHash()));
    }

    public function testRunRejectsRecentlyUsedPassword(): void
    {
        $config = VoytiConfigFactory::create(maxPasswordAge: 90);
        $user = $this->createUser(username: 'reuseuser', email: 'reuse@example.com', passwordHash: 'oldhash');

        $this->createService(config: $config)->run('newpassword', $user, null);
        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);

        $result = $this->createService(config: $config)->run('newpassword', $reloaded, null);

        self::assertFalse($result);
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

    private function createService(?EventDispatcherInterface $eventDispatcher = null, ?VoytiConfig $config = null): ResetService
    {
        $eventDispatcher ??= $this->createMock(EventDispatcherInterface::class);
        $config ??= VoytiConfigFactory::create();
        $passwordHasher = TestPasswordHasherFactory::create();

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
