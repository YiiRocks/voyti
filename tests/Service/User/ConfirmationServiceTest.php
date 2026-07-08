<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ConfirmationServiceTest extends TestCase
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

    public function testRunAlreadyConfirmedReturnsFalse(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $userTokenRepository = new UserTokenRepository();
        $service = new ConfirmationService($eventDispatcher, $userTokenRepository);

        $user = new User();
        $user->setUsername('confirmed');
        $user->setEmail('confirmed@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setConfirmedAt(time());
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        self::assertFalse($service->run($user));
    }

    public function testRunDeletesUserTokens(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');
        $userTokenRepository = new UserTokenRepository();
        $service = new ConfirmationService($eventDispatcher, $userTokenRepository);

        $user = new User();
        $user->setUsername('delete_tokens');
        $user->setEmail('delete_tokens@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $userToken = new UserToken();
        $userToken->setUserId((int) $user->getId());
        $userToken->setCode('confirm_token');
        $userToken->setType(UserToken::TYPE_CONFIRMATION);
        $userToken->setCreatedAt(time());
        $userToken->save();

        self::assertTrue($service->run($user));

        self::assertEmpty($userTokenRepository->findByUserId((int) $user->getId()));
    }

    public function testRunPersistsConfirmation(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');
        $userTokenRepository = new UserTokenRepository();
        $service = new ConfirmationService($eventDispatcher, $userTokenRepository);

        $user = new User();
        $user->setUsername('persist_confirm');
        $user->setEmail('persist_confirm@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        self::assertTrue($service->run($user));

        $reloaded = (new UserRepository())->findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getConfirmedAt());
    }

    public function testRunSuccess(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');
        $userTokenRepository = new UserTokenRepository();
        $service = new ConfirmationService($eventDispatcher, $userTokenRepository);

        $user = new User();
        $user->setUsername('unconfirmed');
        $user->setEmail('unconfirmed@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        self::assertTrue($service->run($user));
        self::assertNotNull($user->getConfirmedAt());
    }
}
