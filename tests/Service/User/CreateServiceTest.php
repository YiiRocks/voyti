<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\User\CreateService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\EventCaptureDispatcher;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\ThrowingEventDispatcher;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use Yiisoft\View\View;

#[AllowMockObjectsWithoutExpectations]
final class CreateServiceTest extends DatabaseTestCase
{
    public function testRunErrors(): void
    {
        // Email already exists
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
        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config), $this->createTranslator());
        $service = new CreateService($userCreationHelper);
        $result = $service->run('existing@example.com', 'testuser', 'password123');
        self::assertTrue($result->isFailure());
        self::assertSame('Email already exists', $result->getMessage());

        // Race condition: uniqueness passes but persistence fails
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create();
        $userCreationHelper = new UserCreationHelper(
            $this->createMailService(new MailCapture()),
            new ThrowingEventDispatcher('Email already exists'),
            $passwordHasher,
            $config,
            new PasswordHistoryService($passwordHasher, $config),
            $this->createTranslator(),
        );
        $service = new CreateService($userCreationHelper);
        $result = $service->run('race@example.com', 'raceuser', 'password123');
        self::assertTrue($result->isFailure());
        self::assertSame('Email already exists', $result->getMessage());
    }

    public function testRunWithEmailConfirmation(): void
    {
        // Confirmation disabled: user immediately confirmed
        $mailCapture = new MailCapture();
        $mailService = $this->createMailService($mailCapture);
        $eventDispatcher = new EventCaptureDispatcher();
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create(enableEmailConfirmation: false);
        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config), $this->createTranslator());
        $service = new CreateService($userCreationHelper);
        $result = $service->run('disabled@example.com', 'testuser1', 'password123');
        self::assertTrue($result->isSuccess());
        self::assertSame('User has been created', $result->getMessage());
        $foundUser = User::findByEmail('disabled@example.com');
        self::assertNotNull($foundUser);
        self::assertNotNull($foundUser->getConfirmedAt());
        self::assertSame('testuser1', $foundUser->getUsername());
        self::assertNotEmpty($foundUser->getPasswordHash());
        self::assertNotEmpty($foundUser->getAuthKey());
        self::assertGreaterThan(0, $foundUser->getCreatedAt());
        self::assertGreaterThan(0, $foundUser->getUpdatedAt());
        self::assertNotEmpty($mailCapture->getSentMessages());
        self::assertCount(2, $eventDispatcher->getEvents());
        $userEvent = $eventDispatcher->getEvent(UserEvent::class);
        self::assertNotNull($userEvent);
        self::assertSame(UserEvent::CREATE, $userEvent->getType());

        // Confirmation enabled: user pending confirmation with token
        $mailCapture = new MailCapture();
        $mailService = $this->createMailService($mailCapture);
        $eventDispatcher = new EventCaptureDispatcher();
        $passwordHasher = TestPasswordHasherFactory::create();
        $config = VoytiConfigFactory::create(enableEmailConfirmation: true);
        $userCreationHelper = new UserCreationHelper($mailService, $eventDispatcher, $passwordHasher, $config, new PasswordHistoryService($passwordHasher, $config), $this->createTranslator());
        $service = new CreateService($userCreationHelper);
        $result = $service->run('enabled@example.com', 'testuser2', 'password123');
        self::assertTrue($result->isSuccess());
        self::assertSame('User has been created', $result->getMessage());
        $foundUser = User::findByEmail('enabled@example.com');
        self::assertNotNull($foundUser);
        self::assertNull($foundUser->getConfirmedAt());
        self::assertSame('testuser2', $foundUser->getUsername());
        self::assertNotEmpty($foundUser->getPasswordHash());
        self::assertNotEmpty($foundUser->getAuthKey());
        self::assertGreaterThan(0, $foundUser->getCreatedAt());
        self::assertGreaterThan(0, $foundUser->getUpdatedAt());
        $tokens = UserToken::findByUserId((int) $foundUser->getId());
        self::assertCount(1, $tokens);
        $userToken = $tokens[0];
        self::assertGreaterThan(0, $userToken->getCreatedAt());
        self::assertSame(64, strlen($userToken->getCode()));
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
            new View(),
            $this->createTranslator(),
            new FakeUrlGenerator(),
        );
    }
}
