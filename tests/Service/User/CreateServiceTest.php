<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\User\CreateService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class CreateServiceTest extends TestCase
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

    public function testRunEmailAlreadyExistsReturnsFailure(): void
    {
        $existing = new User();
        $existing->setUsername('existing');
        $existing->setEmail('existing@example.com');
        $existing->setPasswordHash('hash');
        $existing->setAuthKey('key');
        $existing->setCreatedAt(time());
        $existing->setUpdatedAt(time());
        $existing->save();

        $userRepository = new UserRepository();
        $mailService = $this->createMailService(new MailCapture());
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = new PasswordHasher();
        $config = new ModuleConfig();

        $service = new CreateService($userRepository, $mailService, $eventDispatcher, $passwordHasher, $config);
        $result = $service->run('existing@example.com', 'testuser', 'password123');

        self::assertTrue($result->isFailure());
        self::assertSame('Email already exists', $result->getMessage());
    }

    public function testRunUsernameAlreadyExistsReturnsFailure(): void
    {
        $existing = new User();
        $existing->setUsername('existinguser');
        $existing->setEmail('other@example.com');
        $existing->setPasswordHash('hash');
        $existing->setAuthKey('key');
        $existing->setCreatedAt(time());
        $existing->setUpdatedAt(time());
        $existing->save();

        $userRepository = new UserRepository();
        $mailService = $this->createMailService(new MailCapture());
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = new PasswordHasher();
        $config = new ModuleConfig();

        $service = new CreateService($userRepository, $mailService, $eventDispatcher, $passwordHasher, $config);
        $result = $service->run('new@example.com', 'existinguser', 'password123');

        self::assertTrue($result->isFailure());
        self::assertSame('Username already exists', $result->getMessage());
    }

    public function testRunWithEmailConfirmationDisabled(): void
    {
        $userRepository = new UserRepository();
        $mailCapture = new MailCapture();
        $mailService = $this->createMailService($mailCapture);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');
        $passwordHasher = new PasswordHasher();
        $config = new ModuleConfig(enableEmailConfirmation: false);

        $service = new CreateService($userRepository, $mailService, $eventDispatcher, $passwordHasher, $config);
        $result = $service->run('new@example.com', 'testuser', 'password123');

        self::assertTrue($result->isSuccess());
        self::assertSame('User has been created', $result->getMessage());

        $foundUser = $userRepository->findByEmail('new@example.com');
        self::assertNotNull($foundUser);
        self::assertNotNull($foundUser->getConfirmedAt());
        self::assertSame('testuser', $foundUser->getUsername());
        self::assertNotEmpty($foundUser->getPasswordHash());
        self::assertNotEmpty($foundUser->getAuthKey());
        self::assertGreaterThan(0, $foundUser->getCreatedAt());
        self::assertGreaterThan(0, $foundUser->getUpdatedAt());
        self::assertNotEmpty($mailCapture->getSentMessages());
    }

    public function testRunWithEmailConfirmationEnabled(): void
    {
        $userRepository = new UserRepository();
        $mailCapture = new MailCapture();
        $mailService = $this->createMailService($mailCapture);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))->method('dispatch');
        $passwordHasher = new PasswordHasher();
        $config = new ModuleConfig(enableEmailConfirmation: true);

        $service = new CreateService($userRepository, $mailService, $eventDispatcher, $passwordHasher, $config);
        $result = $service->run('new@example.com', 'testuser', 'password123');

        self::assertTrue($result->isSuccess());
        self::assertSame('User has been created', $result->getMessage());

        $foundUser = $userRepository->findByEmail('new@example.com');
        self::assertNotNull($foundUser);
        self::assertNull($foundUser->getConfirmedAt());
        self::assertSame('testuser', $foundUser->getUsername());
        self::assertNotEmpty($foundUser->getPasswordHash());
        self::assertNotEmpty($foundUser->getAuthKey());
        self::assertGreaterThan(0, $foundUser->getCreatedAt());
        self::assertGreaterThan(0, $foundUser->getUpdatedAt());

        $userTokenRepository = new UserTokenRepository();
        $tokens = $userTokenRepository->findByUserId((int) $foundUser->getId());
        self::assertCount(1, $tokens);
        $userToken = $tokens[0];
        self::assertGreaterThan(0, $userToken->getCreatedAt());
        self::assertSame(32, strlen($userToken->getCode()));
        self::assertNotEmpty($mailCapture->getSentMessages());
    }

    private function createMailService(MailCapture $mailCapture): MailService
    {
        return new MailService(
            $mailCapture,
            '',
            $this->createMock(TranslatorInterface::class),
            new FakeUrlGenerator(),
        );
    }
}
