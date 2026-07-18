<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;

#[AllowMockObjectsWithoutExpectations]
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

    public function testConfirmWithCodeAlreadyConfirmedReturnsFalse(): void
    {
        $user = $this->createUser('confirmed', 'confirmed@example.com');
        $user->setConfirmedAt(time());
        $user->save();

        self::assertFalse($this->createService()->confirmWithCode('code', $user));
    }

    public function testConfirmWithCodeServiceFailureReturnsFalse(): void
    {
        $user = $this->createUser('fail', 'fail@example.com');
        $token = new UserToken();
        $token->setUserId((int) $user->getId());
        $token->setCode('validcode');
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());
        $token->save();

        $confirmationService = $this->getMockBuilder(ConfirmationService::class)
            ->setConstructorArgs([
                $this->createMock(EventDispatcherInterface::class),
                new UserTokenFactory(),
                $this->createMock(MailService::class),
            ])
            ->onlyMethods(['run'])
            ->getMock();
        $confirmationService->method('run')->willReturn(false);

        self::assertFalse($confirmationService->confirmWithCode('validcode', $user));
    }

    public function testConfirmWithCodeSuccess(): void
    {
        $user = $this->createUser('success', 'success@example.com');
        $token = new UserToken();
        $token->setUserId((int) $user->getId());
        $token->setCode('successcode');
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());
        $token->save();

        self::assertTrue($this->createService()->confirmWithCode('successcode', $user));

        $foundToken = UserToken::findByUserIdAndCode((int) $user->getId(), 'successcode');
        self::assertNull($foundToken);
    }

    public function testConfirmWithCodeTokenExpiredReturnsFalse(): void
    {
        $user = $this->createUser('expired', 'expired@example.com');
        $token = new UserToken();
        $token->setUserId((int) $user->getId());
        $token->setCode('expiredcode');
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(time() - 200000);
        $token->save();

        self::assertFalse($this->createService()->confirmWithCode('expiredcode', $user));
    }

    public function testConfirmWithCodeTokenNotFoundReturnsFalse(): void
    {
        $user = $this->createUser('no-token', 'notoken@example.com');

        self::assertFalse($this->createService()->confirmWithCode('nonexistent', $user));
    }

    public function testResendAlreadyConfirmedReturnsFalse(): void
    {
        $user = $this->createUser('confirmed', 'confirmed@example.com');
        $user->setConfirmedAt(time());
        $user->save();

        $tokenFactory = new UserTokenFactory();
        $mailService = $this->createMock(MailService::class);
        $service = new ConfirmationService(
            $this->createMock(EventDispatcherInterface::class),
            $tokenFactory,
            $mailService,
        );

        self::assertFalse($service->resend($user));
    }

    public function testResendSuccess(): void
    {
        $user = $this->createUser('unconfirmed', 'unconfirmed@example.com');
        $tokenFactory = new UserTokenFactory();
        $mailService = $this->createMock(MailService::class);
        $mailService->method('sendConfirmation')->willReturn(true);
        $service = new ConfirmationService(
            $this->createMock(EventDispatcherInterface::class),
            $tokenFactory,
            $mailService,
        );

        self::assertTrue($service->resend($user));
    }

    public function testRunAlreadyConfirmedReturnsFalse(): void
    {
        $user = $this->createUser('confirmed', 'confirmed@example.com');
        $user->setConfirmedAt(time());
        $user->save();

        self::assertFalse($this->createService()->run($user));
    }

    public function testRunDeletesOnlyConfirmationTokens(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(1))->method('dispatch');

        $user = $this->createUser('delete_tokens', 'delete_tokens@example.com');
        $userId = (int) $user->getId();

        $confirmationToken = new UserToken();
        $confirmationToken->setUserId($userId);
        $confirmationToken->setCode('confirm_token');
        $confirmationToken->setType(UserToken::TYPE_CONFIRMATION);
        $confirmationToken->setCreatedAt(time());
        $confirmationToken->save();

        $recoveryToken = new UserToken();
        $recoveryToken->setUserId($userId);
        $recoveryToken->setCode('recovery_token');
        $recoveryToken->setType(UserToken::TYPE_RECOVERY);
        $recoveryToken->setCreatedAt(time());
        $recoveryToken->save();

        self::assertTrue($this->createService($eventDispatcher)->run($user));

        $remaining = UserToken::findByUserId($userId);
        self::assertCount(1, $remaining);
        self::assertSame(UserToken::TYPE_RECOVERY, $remaining[0]->getType());
    }

    public function testRunPersistsConfirmation(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(1))->method('dispatch');

        $user = $this->createUser('persist_confirm', 'persist_confirm@example.com');

        self::assertTrue($this->createService($eventDispatcher)->run($user));

        $reloaded = User::findById((int) $user->getId());
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getConfirmedAt());
    }

    public function testRunSuccess(): void
    {
        $eventDispatcher = new EventCaptureDispatcher();

        $user = $this->createUser('unconfirmed', 'unconfirmed@example.com');

        self::assertTrue($this->createService($eventDispatcher)->run($user));
        self::assertNotNull($user->getConfirmedAt());
        self::assertCount(1, $eventDispatcher->getEvents());
        $event = $eventDispatcher->getEvent(UserEvent::class);
        self::assertNotNull($event);
        self::assertSame(UserEvent::CONFIRM, $event->getType());
    }

    private function createService(?EventDispatcherInterface $eventDispatcher = null): ConfirmationService
    {
        return new ConfirmationService(
            $eventDispatcher ?? $this->createMock(EventDispatcherInterface::class),
            new UserTokenFactory(),
            $this->createMock(MailService::class),
        );
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
