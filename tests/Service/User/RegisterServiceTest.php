<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserPasswordHistory;
use YiiRocks\Voyti\Model\UserProfile;
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

    public function testRunDataProcessingConsent(): void
    {
        $mailService = $this->createMailService(new MailCapture());
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create(enableEmailConfirmation: true);

        // Consent always stored when provided
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config);
        $result = $service->run(['email' => 'consent@example.com', 'username' => 'consentuser', 'password' => 'mypassword', 'dataProcessingConsent' => '1']);
        self::assertTrue($result->isSuccess());
        $saved = User::findByEmail('consent@example.com');
        self::assertTrue($saved->hasDataProcessingConsent());
        self::assertNotNull($saved->getDataProcessingConsentDate());
    }

    public function testRunErrors(): void
    {
        $mailService = $this->createMailService(new MailCapture());
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create();

        // Email already exists: short-circuits before user creation
        $existing = new User();
        $existing->setUsername('existing');
        $existing->setEmail('existing@example.com');
        $existing->setPasswordHash('hash');
        $existing->setAuthKey('key');
        $existing->setCreatedAt(time());
        $existing->setUpdatedAt(time());
        $existing->save();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config);
        $result = $service->run(['email' => 'existing@example.com', 'username' => 'testuser']);
        self::assertTrue($result->isFailure());
        self::assertSame('Email already exists', $result->getMessage());
        self::assertNull(User::findByUsername('testuser'));

        // Username already exists
        $existing2 = new User();
        $existing2->setUsername('existinguser');
        $existing2->setEmail('other@example.com');
        $existing2->setPasswordHash('hash');
        $existing2->setAuthKey('key');
        $existing2->setCreatedAt(time());
        $existing2->setUpdatedAt(time());
        $existing2->save();
        $eventDispatcher2 = $this->createMock(EventDispatcherInterface::class);
        $userCreationHelper2 = new UserCreationHelper($mailService, $eventDispatcher2, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service2 = new RegisterService($userCreationHelper2, $config);
        $result2 = $service2->run(['email' => 'new@example.com', 'username' => 'existinguser']);
        self::assertTrue($result2->isFailure());
        self::assertSame('Username already exists', $result2->getMessage());

        // Race condition: uniqueness passes but persistence fails
        $userCreationHelper3 = new UserCreationHelper(
            $mailService,
            new ThrowingEventDispatcher('Email already exists'),
            $passwordHasher,
            $config,
            new PasswordHistoryService($passwordHasher, $config),
        );
        $service3 = new RegisterService($userCreationHelper3, $config);
        $result3 = $service3->run(['email' => 'race@example.com', 'username' => 'raceuser', 'password' => 'secret123']);
        self::assertTrue($result3->isFailure());
        self::assertSame('Email already exists', $result3->getMessage());
        self::assertSame(['Email already exists'], $result3->getErrors());
    }

    public function testRunPassword(): void
    {
        $mailService = $this->createMailService(new MailCapture());
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create(enableEmailConfirmation: true, maxPasswordAge: 90);

        // User-provided password: records in history and creates profile
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config);
        $result = $service->run(['email' => 'userpass@example.com', 'username' => 'userpassuser', 'password' => 'userpassword123'], ['REMOTE_ADDR' => '203.0.113.9']);
        self::assertTrue($result->isSuccess());
        // Confirmation required: persist() must report true, not just an empty success.
        self::assertSame('voyti.registration.account_created_check_email', $result->getMessage());
        $saved = User::findByEmail('userpass@example.com');
        self::assertSame('203.0.113.9', $saved->getRegistrationIp());
        self::assertNotEmpty(UserPasswordHistory::findByUserId((int) $saved->getId()));
        self::assertNotNull(UserProfile::findByUserId((int) $saved->getId()));
    }

    public function testRunWithMissingDataDefaultsToEmptyStrings(): void
    {
        $mailService = $this->createMailService(new MailCapture());
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create(enableEmailConfirmation: true);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config);

        // None of username/email/password keys are present: each must default to '' rather than
        // triggering an undefined-array-key access (which would emit a warning under a mutated
        // isset()||is_string() check, since the guarded key is never dereferenced when absent).
        $result = $service->run([]);

        self::assertTrue($result->isSuccess());
        $saved = User::findByEmail('');
        self::assertNotNull($saved);
        self::assertSame('', $saved->getUsername());
    }

    public function testRunWithoutEmailConfirmation(): void
    {
        $mailService = $this->createMailService(new MailCapture());
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create(enableEmailConfirmation: false, maxPasswordAge: 90);

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new RegisterService($userCreationHelper, $config);

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
}
