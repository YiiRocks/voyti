<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\Service\User\ConfirmationService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\MailServiceFactoryTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;

#[AllowMockObjectsWithoutExpectations]
final class ConfirmationServiceTest extends DatabaseTestCase
{
    use MailServiceFactoryTrait;
    use UserFactoryTrait;

    public function testConfirmWithCodeAlreadyConfirmedReturnsFalse(): void
    {
        $user = $this->createUser('confirmed', 'confirmed@example.com');
        $user->setConfirmedAt(time());
        $user->save();

        self::assertFalse($this->createService()->confirmWithCode('code', $user));
    }

    public function testConfirmWithCodeSuccess(): void
    {
        $user = $this->createUser('success', 'success@example.com');
        $token = new UserToken();
        $token->setUserId((int) $user->getId());
        $token->setCode(hash('sha256', 'successcode'));
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
        $token->setCode(hash('sha256', 'expiredcode'));
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(time() - 200000);
        $token->save();

        self::assertFalse($this->createService()->confirmWithCode('expiredcode', $user));
    }

    public function testResendAlreadyConfirmedReturnsFalse(): void
    {
        $user = $this->createUser('confirmed', 'confirmed@example.com');
        $user->setConfirmedAt(time());
        $user->save();

        $tokenFactory = new UserTokenFactory();
        $mailService = $this->createMailService(new MailCapture());
        $service = new ConfirmationService(
            $this->createMock(EventDispatcherInterface::class),
            $tokenFactory,
            $mailService,
        );

        self::assertFalse($service->resend($user));
    }

    public function testResendDeletesOnlyConfirmationTokens(): void
    {
        $user = $this->createUser('resend_delete_tokens', 'resend_delete_tokens@example.com');
        $userId = (int) $user->getId();

        $confirmationToken = new UserToken();
        $confirmationToken->setUserId($userId);
        $confirmationToken->setCode('old_confirm_token');
        $confirmationToken->setType(UserToken::TYPE_CONFIRMATION);
        $confirmationToken->setCreatedAt(time());
        $confirmationToken->save();

        $recoveryToken = new UserToken();
        $recoveryToken->setUserId($userId);
        $recoveryToken->setCode('recovery_token');
        $recoveryToken->setType(UserToken::TYPE_RECOVERY);
        $recoveryToken->setCreatedAt(time());
        $recoveryToken->save();

        $mailService = $this->createMailService(new MailCapture());
        $service = new ConfirmationService(
            $this->createMock(EventDispatcherInterface::class),
            new UserTokenFactory(),
            $mailService,
        );

        self::assertTrue($service->resend($user));

        $remaining = UserToken::findByUserId($userId);
        self::assertCount(2, $remaining);
        $remainingTypes = array_map(static fn(UserToken $token): int => $token->getType(), $remaining);
        self::assertContains(UserToken::TYPE_RECOVERY, $remainingTypes);
    }

    public function testResendSuccess(): void
    {
        $user = $this->createUser('unconfirmed', 'unconfirmed@example.com');
        $tokenFactory = new UserTokenFactory();
        $mailCapture = new MailCapture();
        $mailService = $this->createMailService($mailCapture);
        $service = new ConfirmationService(
            $this->createMock(EventDispatcherInterface::class),
            $tokenFactory,
            $mailService,
        );

        self::assertTrue($service->resend($user));
        // A confirmation email is actually sent.
        self::assertCount(1, $mailCapture->getSentMessages());
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

    private function createService(?EventDispatcherInterface $eventDispatcher = null): ConfirmationService
    {
        return new ConfirmationService(
            $eventDispatcher ?? $this->createMock(EventDispatcherInterface::class),
            new UserTokenFactory(),
            $this->createMailService(new MailCapture()),
        );
    }
}
