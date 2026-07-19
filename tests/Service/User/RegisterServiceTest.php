<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;

#[AllowMockObjectsWithoutExpectations]
final class RegisterServiceTest extends TestCase
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

        $mailService = $this->createMock(MailService::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = new ModuleConfig();
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);
        $result = $service->run(['email' => 'existing@example.com', 'username' => 'testuser']);

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

        $mailService = $this->createMock(MailService::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = new ModuleConfig();
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);
        $result = $service->run(['email' => 'new@example.com', 'username' => 'existinguser']);

        self::assertTrue($result->isFailure());
        self::assertSame('Username already exists', $result->getMessage());
    }

    public function testRunWithDisabledIpLogging(): void
    {
        $mailService = $this->createMock(MailService::class);
        $mailService->method('sendConfirmation')->willReturn(true);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = new ModuleConfig(enableEmailConfirmation: true, disableIpLogging: true);
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->method('generate')->willReturn('genpwd');

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);

        $result = $service->run(['email' => 'ipdisabled@example.com', 'username' => 'ipdisableduser', 'password' => 'mypassword']);

        self::assertTrue($result->isSuccess());
    }

    public function testRunWithGdprConsentEnabledAndConsentGiven(): void
    {
        $mailService = $this->createMock(MailService::class);
        $mailService->method('sendConfirmation')->willReturn(true);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = new ModuleConfig(enableEmailConfirmation: true, enableGdprCompliance: true);
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->method('generate')->willReturn('genpwd');

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);

        $result = $service->run([
            'email' => 'gdpr@example.com',
            'username' => 'gdpruser',
            'password' => 'mypassword',
            'gdprConsent' => true,
        ]);

        self::assertTrue($result->isSuccess());
        $saved = User::findByEmail('gdpr@example.com');
        self::assertNotNull($saved);
        self::assertTrue($saved->isGdprConsent());
        self::assertNotNull($saved->getGdprConsentDate());
    }

    public function testRunWithGdprConsentEnabledButNotGiven(): void
    {
        $mailService = $this->createMock(MailService::class);
        $mailService->method('sendConfirmation')->willReturn(true);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = new ModuleConfig(enableEmailConfirmation: true, enableGdprCompliance: true);
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->method('generate')->willReturn('genpwd');

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);

        $result = $service->run([
            'email' => 'nogdpr@example.com',
            'username' => 'nogdpruser',
            'password' => 'mypassword',
            'gdprConsent' => false,
        ]);

        self::assertTrue($result->isSuccess());
        $saved = User::findByEmail('nogdpr@example.com');
        self::assertNotNull($saved);
        self::assertFalse($saved->isGdprConsent());
        self::assertNull($saved->getGdprConsentDate());
    }

    public function testRunWithGeneratedPassword(): void
    {
        $mailService = $this->createMock(MailService::class);
        $mailService->method('sendConfirmation')->willReturn(true);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = new ModuleConfig(enableEmailConfirmation: true);
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->method('generate')->willReturn('auto-generated-pwd');

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);

        $result = $service->run(['email' => 'genpass@example.com', 'username' => 'genpassuser', 'password' => '']);

        self::assertTrue($result->isSuccess());
        self::assertSame('voyti.registration.account_created_check_email', $result->getMessage());
    }

    public function testRunWithMissingDataFallsBackToEmptyDefaults(): void
    {
        $mailService = $this->createMock(MailService::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = new ModuleConfig(enableEmailConfirmation: true);
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->method('generate')->willReturn('genpwd');

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);

        $result = $service->run([]);

        self::assertTrue($result->isSuccess());
        self::assertSame('voyti.registration.account_created_check_email', $result->getMessage());
    }

    public function testRunWithoutEmailConfirmation(): void
    {
        $mailService = $this->createMock(MailService::class);
        $mailService->method('sendWelcome')->willReturn(true);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = new ModuleConfig(enableEmailConfirmation: false);
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->method('generate')->willReturn('genpwd');

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);

        $result = $service->run(['email' => 'noconfirm@example.com', 'username' => 'noconfirmuser', 'password' => 'mypassword']);

        self::assertTrue($result->isSuccess());
        self::assertSame('voyti.registration.account_created', $result->getMessage());

        $saved = User::findByEmail('noconfirm@example.com');
        self::assertNotNull($saved);
        self::assertNotNull($saved->getConfirmedAt());
    }

    public function testRunWithUserProvidedPassword(): void
    {
        $mailService = $this->createMock(MailService::class);
        $mailService->method('sendConfirmation')->willReturn(true);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = new ModuleConfig(enableEmailConfirmation: true);
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->expects($this->never())->method('generate');

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);

        $result = $service->run(['email' => 'userpass@example.com', 'username' => 'userpassuser', 'password' => 'userpassword123']);

        self::assertTrue($result->isSuccess());
    }
}
