<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\MailServiceFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;

#[AllowMockObjectsWithoutExpectations]
final class RecoveryServiceTest extends DatabaseTestCase
{
    use MailServiceFactoryTrait;

    public function testRunWithBlockedUserReturnsGenericSuccess(): void
    {
        $user = new User();
        $user->setUsername('blockeduser');
        $user->setEmail('blocked@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setBlockedAt(time());
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $userTokenFactory = new UserTokenFactory();
        $mailService = $this->createMailService(new MailCapture());
        $config = VoytiConfigFactory::create();
        $translator = $this->createTranslator();

        $service = new RecoveryService(
            $userTokenFactory,
            $mailService,
            $config,
            $translator,
        );

        $result = $service->run('blocked@example.com');
        self::assertTrue($result->isSuccess());
    }

    public function testRunWithUnknownEmailReturnsGenericSuccess(): void
    {
        $userTokenFactory = new UserTokenFactory();
        $mailService = $this->createMailService(new MailCapture());
        $config = VoytiConfigFactory::create();
        $translator = $this->createTranslator();

        $service = new RecoveryService(
            $userTokenFactory,
            $mailService,
            $config,
            $translator,
        );

        $result = $service->run('unknown@example.com');
        self::assertTrue($result->isSuccess());
        self::assertSame('If the email exists, a recovery message has been sent', $result->getMessage());
    }

    public function testRunWithValidUserSendsRecovery(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('valid@example.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(time());
        $user->setUpdatedAt(time());
        $user->save();

        $userTokenFactory = new UserTokenFactory();
        $mailCapture = new MailCapture();
        $mailService = $this->createMailService($mailCapture);
        $config = VoytiConfigFactory::create();
        $translator = $this->createTranslator();

        $service = new RecoveryService(
            $userTokenFactory,
            $mailService,
            $config,
            $translator,
        );

        $result = $service->run('valid@example.com');
        self::assertTrue($result->isSuccess());
        self::assertSame('Recovery message sent', $result->getMessage());
        // A recovery email is actually sent to the user.
        self::assertCount(1, $mailCapture->getSentMessages());
    }
}
