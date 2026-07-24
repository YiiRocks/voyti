<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class RecoveryServiceTest extends TestCase
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
        $mailService = $this->createMock(MailService::class);
        $config = ModuleConfigFactory::create();
        $translator = $this->createTranslator();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new RecoveryService(
            $userTokenFactory,
            $mailService,
            $config,
            $translator,
            $eventDispatcher,
        );

        $result = $service->run('blocked@example.com');
        self::assertTrue($result->isSuccess());
    }

    public function testRunWithUnknownEmailReturnsGenericSuccess(): void
    {
        $userTokenFactory = new UserTokenFactory();
        $mailService = $this->createMock(MailService::class);
        $config = ModuleConfigFactory::create();
        $translator = $this->createTranslator();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new RecoveryService(
            $userTokenFactory,
            $mailService,
            $config,
            $translator,
            $eventDispatcher,
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
        $mailService = $this->createMock(MailService::class);
        $mailService->method('sendRecovery')->willReturn(true);
        $config = ModuleConfigFactory::create();
        $translator = $this->createTranslator();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new RecoveryService(
            $userTokenFactory,
            $mailService,
            $config,
            $translator,
            $eventDispatcher,
        );

        $result = $service->run('valid@example.com');
        self::assertTrue($result->isSuccess());
        self::assertSame('Recovery message sent', $result->getMessage());
    }
}
