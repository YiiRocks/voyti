<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\User\CreateService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
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

        $mailService = $this->createMailService(new MailCapture());
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = new ModuleConfig();

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new CreateService($userCreationHelper);
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

        $mailService = $this->createMailService(new MailCapture());
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = new ModuleConfig();

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new CreateService($userCreationHelper);
        $result = $service->run('new@example.com', 'existinguser', 'password123');

        self::assertTrue($result->isFailure());
        self::assertSame('Username already exists', $result->getMessage());
    }

    public function testRunWithEmailConfirmationDisabled(): void
    {
        $mailCapture = new MailCapture();
        $mailService = $this->createMailService($mailCapture);
        $eventDispatcher = new EventCaptureDispatcher();
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = new ModuleConfig(enableEmailConfirmation: false);

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new CreateService($userCreationHelper);
        $result = $service->run('new@example.com', 'testuser', 'password123');

        self::assertTrue($result->isSuccess());
        self::assertSame('User has been created', $result->getMessage());

        $foundUser = User::findByEmail('new@example.com');
        self::assertNotNull($foundUser);
        self::assertNotNull($foundUser->getConfirmedAt());
        self::assertSame('testuser', $foundUser->getUsername());
        self::assertNotEmpty($foundUser->getPasswordHash());
        self::assertNotEmpty($foundUser->getAuthKey());
        self::assertGreaterThan(0, $foundUser->getCreatedAt());
        self::assertGreaterThan(0, $foundUser->getUpdatedAt());
        self::assertNotEmpty($mailCapture->getSentMessages());
        self::assertCount(2, $eventDispatcher->getEvents());
        $userEvent = $eventDispatcher->getEvent(UserEvent::class);
        self::assertNotNull($userEvent);
        self::assertSame(UserEvent::CREATE, $userEvent->getType());
    }

    public function testRunWithEmailConfirmationEnabled(): void
    {
        $mailCapture = new MailCapture();
        $mailService = $this->createMailService($mailCapture);
        $eventDispatcher = new EventCaptureDispatcher();
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = new ModuleConfig(enableEmailConfirmation: true);

        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config));
        $service = new CreateService($userCreationHelper);
        $result = $service->run('new@example.com', 'testuser', 'password123');

        self::assertTrue($result->isSuccess());
        self::assertSame('User has been created', $result->getMessage());

        $foundUser = User::findByEmail('new@example.com');
        self::assertNotNull($foundUser);
        self::assertNull($foundUser->getConfirmedAt());
        self::assertSame('testuser', $foundUser->getUsername());
        self::assertNotEmpty($foundUser->getPasswordHash());
        self::assertNotEmpty($foundUser->getAuthKey());
        self::assertGreaterThan(0, $foundUser->getCreatedAt());
        self::assertGreaterThan(0, $foundUser->getUpdatedAt());

        $tokens = UserToken::findByUserId((int) $foundUser->getId());
        self::assertCount(1, $tokens);
        $userToken = $tokens[0];
        self::assertGreaterThan(0, $userToken->getCreatedAt());
        self::assertSame(32, strlen($userToken->getCode()));
        self::assertNotEmpty($mailCapture->getSentMessages());
        self::assertCount(2, $eventDispatcher->getEvents());
        $userEvent = $eventDispatcher->getEvent(UserEvent::class);
        self::assertNotNull($userEvent);
        self::assertSame(UserEvent::CREATE, $userEvent->getType());
    }

    private function createMailService(MailCapture $mailCapture): MailService
    {
        return new MailService(
            $mailCapture,
            '',
            $this->createTranslator(),
            new FakeUrlGenerator(),
        );
    }
}
