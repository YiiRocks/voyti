<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserPasswordHistory;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\Service\Password\PasswordGeneratorInterface;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\User\RegisterService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\MailServiceFactoryTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\ThrowingEventDispatcher;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;

#[AllowMockObjectsWithoutExpectations]
final class RegisterServiceTest extends DatabaseTestCase
{
    use MailServiceFactoryTrait;

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

        $mailService = $this->createMailService(new MailCapture());
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create();
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);
        $result = $service->run(['email' => 'existing@example.com', 'username' => 'testuser']);

        self::assertTrue($result->isFailure());
        self::assertSame('Email already exists', $result->getMessage());
        // The conflict short-circuits before any user is built: no account with the submitted username exists.
        self::assertNull(User::findByUsername('testuser'));
    }

    public function testRunHandlesRaceLostAfterUniquenessCheckPasses(): void
    {
        // The uniqueness check passes, but persistAndNotify throws (as it would when a concurrent
        // insert wins the race). A real helper whose event dispatch throws exercises run()'s
        // catch branch without mocking the final UserCreationHelper.
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create();
        $userCreationHelper = new UserCreationHelper(
            $this->createMailService(new MailCapture()),
            new ThrowingEventDispatcher('Email already exists'),
            $passwordHasher,
            $config,
            new PasswordHistoryService($passwordHasher, $config),
        );

        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);

        $result = $service->run(['email' => 'race@example.com', 'username' => 'raceuser', 'password' => 'secret123']);

        self::assertTrue($result->isFailure());
        self::assertSame('Email already exists', $result->getMessage());
        self::assertSame(['Email already exists'], $result->getErrors());
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

        $mailService = $this->createMailService(new MailCapture());
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create();
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);
        $result = $service->run(['email' => 'new@example.com', 'username' => 'existinguser']);

        self::assertTrue($result->isFailure());
        self::assertSame('Username already exists', $result->getMessage());
    }

    public function testRunWithGdprComplianceDisabledForcesConsentFalse(): void
    {
        $mailService = $this->createMailService(new MailCapture());
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        // GDPR compliance disabled: even an explicit consent must not be stored as consent.
        $config = VoytiConfigFactory::create(enableEmailConfirmation: true, enableGdprCompliance: false);
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->method('generate')->willReturn('genpwd');

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);

        $result = $service->run([
            'email' => 'disabledgdpr@example.com',
            'username' => 'disabledgdpruser',
            'password' => 'mypassword',
            'gdprConsent' => true,
        ]);

        self::assertTrue($result->isSuccess());
        $saved = User::findByEmail('disabledgdpr@example.com');
        self::assertNotNull($saved);
        self::assertFalse($saved->isGdprConsent());
        self::assertNull($saved->getGdprConsentDate());
    }

    public function testRunWithGdprConsentEnabledAndConsentGiven(): void
    {
        $mailService = $this->createMailService(new MailCapture());
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create(enableEmailConfirmation: true, enableGdprCompliance: true);
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->method('generate')->willReturn('genpwd');

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);

        // A truthy non-bool consent value ('1', as the registration form submits) must be cast to bool
        // before reaching User::setGdprConsent — the raw string would trip the setter's strict types.
        $result = $service->run([
            'email' => 'gdpr@example.com',
            'username' => 'gdpruser',
            'password' => 'mypassword',
            'gdprConsent' => '1',
        ]);

        self::assertTrue($result->isSuccess());
        $saved = User::findByEmail('gdpr@example.com');
        self::assertNotNull($saved);
        self::assertTrue($saved->isGdprConsent());
        self::assertNotNull($saved->getGdprConsentDate());
    }

    public function testRunWithGdprConsentEnabledButConsentKeyAbsent(): void
    {
        $mailService = $this->createMailService(new MailCapture());
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create(enableEmailConfirmation: true, enableGdprCompliance: true);
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->method('generate')->willReturn('genpwd');

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);

        // No gdprConsent key at all: it must default to no-consent, not consent.
        $result = $service->run([
            'email' => 'absentgdpr@example.com',
            'username' => 'absentgdpruser',
            'password' => 'mypassword',
        ]);

        self::assertTrue($result->isSuccess());
        $saved = User::findByEmail('absentgdpr@example.com');
        self::assertNotNull($saved);
        self::assertFalse($saved->isGdprConsent());
        self::assertNull($saved->getGdprConsentDate());
    }

    public function testRunWithGeneratedPassword(): void
    {
        $mailService = $this->createMailService(new MailCapture());
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create(enableEmailConfirmation: true, maxPasswordAge: 90);
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        // The generated password must be requested at the documented 12-char length.
        $passwordGenerator->expects($this->once())->method('generate')->with(12)->willReturn('auto-generated-pwd');

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);

        $result = $service->run(
            ['email' => 'genpass@example.com', 'username' => 'genpassuser', 'password' => ''],
            ['REMOTE_ADDR' => '203.0.113.9'],
        );

        self::assertTrue($result->isSuccess());
        self::assertSame('voyti.registration.account_created_check_email', $result->getMessage());
        $saved = User::findByEmail('genpass@example.com');
        self::assertNotNull($saved);
        // The registration IP from the server params is persisted on the new user.
        self::assertSame('203.0.113.9', $saved->getRegistrationIp());
        // The generated password is recorded in the user's password history.
        self::assertNotEmpty(UserPasswordHistory::findByUserId((int) $saved->getId()));
        // The user's profile row is persisted alongside the user (confirmation path).
        self::assertNotNull(UserProfile::findByUserId((int) $saved->getId()));
    }

    public function testRunWithMissingDataFallsBackToEmptyDefaults(): void
    {
        $mailService = $this->createMailService(new MailCapture());
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create(enableEmailConfirmation: true);
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
        $mailService = $this->createMailService(new MailCapture());
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create(enableEmailConfirmation: false, maxPasswordAge: 90);
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
        // The password is recorded in history on the no-confirmation path too.
        self::assertNotEmpty(UserPasswordHistory::findByUserId((int) $saved->getId()));
        // The user's profile row is persisted alongside the user (no-confirmation path).
        self::assertNotNull(UserProfile::findByUserId((int) $saved->getId()));
    }

    public function testRunWithUserProvidedPassword(): void
    {
        $mailService = $this->createMailService(new MailCapture());
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create(enableEmailConfirmation: true);
        $passwordGenerator = $this->createMock(PasswordGeneratorInterface::class);
        $passwordGenerator->expects($this->never())->method('generate');

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config, $passwordGenerator);

        $result = $service->run(['email' => 'userpass@example.com', 'username' => 'userpassuser', 'password' => 'userpassword123']);

        self::assertTrue($result->isSuccess());
    }
}
