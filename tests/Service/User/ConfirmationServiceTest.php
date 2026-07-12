<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
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
        $user = $this->createUser('confirmed', 'confirmed@example.com');
        $user->setConfirmedAt(time());
        $user->save();

        self::assertFalse($this->createService()->run($user));
    }

    public function testRunDeletesUserTokens(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');

        $user = $this->createUser('delete_tokens', 'delete_tokens@example.com');

        $userToken = new UserToken();
        $userToken->setUserId((int) $user->getId());
        $userToken->setCode('confirm_token');
        $userToken->setType(UserToken::TYPE_CONFIRMATION);
        $userToken->setCreatedAt(time());
        $userToken->save();

        self::assertTrue($this->createService($eventDispatcher)->run($user));

        self::assertEmpty(UserToken::findByUserId((int) $user->getId()));
    }

    public function testRunPersistsConfirmation(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');

        $user = $this->createUser('persist_confirm', 'persist_confirm@example.com');

        self::assertTrue($this->createService($eventDispatcher)->run($user));

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getConfirmedAt());
    }

    public function testRunSuccess(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');

        $user = $this->createUser('unconfirmed', 'unconfirmed@example.com');

        self::assertTrue($this->createService($eventDispatcher)->run($user));
        self::assertNotNull($user->getConfirmedAt());
    }

    private function createService(?EventDispatcherInterface $eventDispatcher = null): ConfirmationService
    {
        return new ConfirmationService($eventDispatcher ?? $this->createMock(EventDispatcherInterface::class));
    }

    private function createUser(string $username, string $email): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        return $user;
    }
}
