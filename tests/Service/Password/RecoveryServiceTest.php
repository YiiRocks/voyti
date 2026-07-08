<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\RecoveryService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
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

        $userRepository = new UserRepository();
        $userTokenRepository = new UserTokenRepository();
        $userTokenFactory = new UserTokenFactory($userTokenRepository);
        $mailService = $this->createMock(MailService::class);
        $config = new ModuleConfig();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturn('If the email exists, a recovery message was sent.');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new RecoveryService(
            $userRepository,
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
        $userRepository = new UserRepository();
        $userTokenRepository = new UserTokenRepository();
        $userTokenFactory = new UserTokenFactory($userTokenRepository);
        $mailService = $this->createMock(MailService::class);
        $config = new ModuleConfig();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturn('If the email exists, a recovery message was sent.');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new RecoveryService(
            $userRepository,
            $userTokenFactory,
            $mailService,
            $config,
            $translator,
            $eventDispatcher,
        );

        $result = $service->run('unknown@example.com');
        self::assertTrue($result->isSuccess());
        self::assertSame('If the email exists, a recovery message was sent.', $result->getMessage());
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

        $userRepository = new UserRepository();
        $userTokenRepository = new UserTokenRepository();
        $userTokenFactory = new UserTokenFactory($userTokenRepository);
        $mailService = $this->createMock(MailService::class);
        $mailService->method('sendRecovery')->willReturn(true);
        $config = new ModuleConfig();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturn('Recovery message sent.');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $service = new RecoveryService(
            $userRepository,
            $userTokenFactory,
            $mailService,
            $config,
            $translator,
            $eventDispatcher,
        );

        $result = $service->run('valid@example.com');
        self::assertTrue($result->isSuccess());
        self::assertSame('Recovery message sent.', $result->getMessage());
    }
}
