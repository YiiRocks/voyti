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

    public function testConfirmWithCode(): void
    {
        // Already confirmed: returns false
        $confirmed = $this->createUser('confirmed', 'confirm_confirmed@example.com');
        $confirmed->setConfirmedAt(time());
        $confirmed->save();
        self::assertFalse($this->createService()->confirmWithCode('code', $confirmed));

        // Success: deletes token
        $success = $this->createUser('success', 'confirm_success@example.com');
        $token = new UserToken();
        $token->setUserId((int) $success->getId());
        $token->setCode(hash('sha256', 'successcode'));
        $token->setType(UserToken::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());
        $token->save();
        self::assertTrue($this->createService()->confirmWithCode('successcode', $success));
        $foundToken = UserToken::findByUserIdAndCode((int) $success->getId(), 'successcode');
        self::assertNull($foundToken);

        // Expired token: returns false
        $expired = $this->createUser('expired', 'confirm_expired@example.com');
        $expiredToken = new UserToken();
        $expiredToken->setUserId((int) $expired->getId());
        $expiredToken->setCode(hash('sha256', 'expiredcode'));
        $expiredToken->setType(UserToken::TYPE_CONFIRMATION);
        $expiredToken->setCreatedAt(time() - 200000);
        $expiredToken->save();
        self::assertFalse($this->createService()->confirmWithCode('expiredcode', $expired));
    }

    public function testResend(): void
    {
        // Already confirmed: returns false
        $confirmed = $this->createUser('resend_confirmed', 'resend_confirmed@example.com');
        $confirmed->setConfirmedAt(time());
        $confirmed->save();
        $tokenFactory = new UserTokenFactory();
        $mailService = $this->createMailService(new MailCapture());
        $service = new ConfirmationService(
            $this->createMock(EventDispatcherInterface::class),
            $tokenFactory,
            $mailService,
        );
        self::assertFalse($service->resend($confirmed));

        // Deletes only confirmation tokens, preserves recovery tokens
        $deleteTokens = $this->createUser('resend_delete_tokens', 'resend_delete_tokens@example.com');
        $userId = (int) $deleteTokens->getId();
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
        self::assertTrue($service->resend($deleteTokens));
        $remaining = UserToken::findByUserId($userId);
        self::assertCount(2, $remaining);
        $remainingTypes = array_map(static fn(UserToken $token): int => $token->getType(), $remaining);
        self::assertContains(UserToken::TYPE_RECOVERY, $remainingTypes);

        // Success: sends email
        $unconfirmed = $this->createUser('resend_unconfirmed', 'resend_unconfirmed@example.com');
        $tokenFactory = new UserTokenFactory();
        $mailCapture = new MailCapture();
        $mailService = $this->createMailService($mailCapture);
        $service = new ConfirmationService(
            $this->createMock(EventDispatcherInterface::class),
            $tokenFactory,
            $mailService,
        );
        self::assertTrue($service->resend($unconfirmed));
        self::assertCount(1, $mailCapture->getSentMessages());
    }

    public function testRun(): void
    {
        // Deletes only confirmation tokens, preserves recovery tokens
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(1))->method('dispatch');
        $deleteTokens = $this->createUser('run_delete_tokens', 'run_delete_tokens@example.com');
        $userId = (int) $deleteTokens->getId();
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
        self::assertTrue($this->createService($eventDispatcher)->run($deleteTokens));
        $remaining = UserToken::findByUserId($userId);
        self::assertCount(1, $remaining);
        self::assertSame(UserToken::TYPE_RECOVERY, $remaining[0]->getType());

        // Persists confirmation
        $persistEventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $persistEventDispatcher->expects($this->exactly(1))->method('dispatch');
        $persistConfirm = $this->createUser('run_persist_confirm', 'run_persist_confirm@example.com');
        self::assertTrue($this->createService($persistEventDispatcher)->run($persistConfirm));
        $reloaded = User::findById((int) $persistConfirm->getId());
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
