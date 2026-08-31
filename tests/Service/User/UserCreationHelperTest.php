<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\MailCapture;
use YiiRocks\Voyti\tests\Support\MailServiceFactoryTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;

/**
 * Covers `UserCreationHelper::persist()`'s handling of the uniqueness-check-then-persist race: two
 * "concurrent" callers both pass `findUniquenessConflict()` before either has saved, so the second
 * `persistAndNotify()` call hits a real DB-level unique-constraint violation.
 */
#[AllowMockObjectsWithoutExpectations]
final class UserCreationHelperTest extends DatabaseTestCase
{
    use MailServiceFactoryTrait;

    public function testPersistAndNotifyLeavesOnlyTheWinningUserPersisted(): void
    {
        $helper = $this->createHelper();

        $winner = $helper->buildUser('race@example.com', 'winner', 'password123');
        $loser = $helper->buildUser('race@example.com', 'loser', 'password456');

        $helper->persistAndNotify($winner);

        try {
            $helper->persistAndNotify($loser);
            self::fail('Expected a RuntimeException to be thrown.');
        } catch (RuntimeException) {
            // Expected - assert the DB state below.
        }

        self::assertNotNull(User::findByUsername('winner'));
        self::assertNull(User::findByUsername('loser'));
    }

    public function testPersistAndNotifySkippingConfirmationPersistsAsAlreadyConfirmed(): void
    {
        // Even with email confirmation required, skipping it (e.g. because an external identity
        // provider already verified the address - the only caller today is
        // yiirocks/voyti-social-auth's auto-registration path) persists the user as confirmed
        // immediately and sends the welcome mail instead of a confirmation token/mail.
        $mailer = new MailCapture();
        $config = VoytiConfigFactory::create(enableEmailConfirmation: true);
        $passwordHasher = TestPasswordHasherFactory::create();
        $helper = new UserCreationHelper(
            $this->createMailService($mailer),
            $this->createMock(EventDispatcherInterface::class),
            $passwordHasher,
            $config,
            new PasswordHistoryService($passwordHasher, $config),
            $this->createTranslator(),
        );

        $user = $helper->buildUser('skip-confirm@example.com', 'skipconfirm', 'password123');

        $requiresConfirmation = $helper->persistAndNotifySkippingConfirmation($user);

        self::assertFalse($requiresConfirmation);
        $saved = User::findByUsername('skipconfirm');
        self::assertNotNull($saved);
        self::assertNotNull($saved->getConfirmedAt());
        self::assertNotNull($mailer->getLastMessage());
        self::assertSame('Welcome to App', $mailer->getLastMessage()->getSubject());
    }

    public function testPersistAndNotifyThrowsWithEmailConflictMessageOnRace(): void
    {
        $helper = $this->createHelper();

        $winner = $helper->buildUser('race@example.com', 'winner', 'password123');
        $loser = $helper->buildUser('race@example.com', 'loser', 'password456');

        self::assertNull($helper->findUniquenessConflict('race@example.com', 'winner'));
        self::assertNull($helper->findUniquenessConflict('race@example.com', 'loser'));

        $helper->persistAndNotify($winner);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Email already exists');
        $helper->persistAndNotify($loser);
    }

    public function testPersistAndNotifyThrowsWithUsernameConflictMessageOnRace(): void
    {
        $helper = $this->createHelper();

        $winner = $helper->buildUser('winner@example.com', 'raceuser', 'password123');
        $loser = $helper->buildUser('loser@example.com', 'raceuser', 'password456');

        $helper->persistAndNotify($winner);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Username already exists');
        $helper->persistAndNotify($loser);
    }

    private function createHelper(): UserCreationHelper
    {
        $config = VoytiConfigFactory::create(enableEmailConfirmation: false);
        $passwordHasher = TestPasswordHasherFactory::create();

        return new UserCreationHelper(
            $this->createMailService(new MailCapture()),
            $this->createMock(EventDispatcherInterface::class),
            $passwordHasher,
            $config,
            new PasswordHistoryService($passwordHasher, $config),
            $this->createTranslator(),
        );
    }
}
