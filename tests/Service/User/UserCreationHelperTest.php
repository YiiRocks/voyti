<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\User;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;

/**
 * Covers `UserCreationHelper::persist()`'s handling of the uniqueness-check-then-persist race: two
 * "concurrent" callers both pass `findUniquenessConflict()` before either has saved, so the second
 * `persistAndNotify()` call hits a real DB-level unique-constraint violation.
 */
#[AllowMockObjectsWithoutExpectations]
final class UserCreationHelperTest extends TestCase
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
            $this->createMock(MailService::class),
            $this->createMock(EventDispatcherInterface::class),
            $passwordHasher,
            $config,
            new PasswordHistoryService($passwordHasher, $config),
        );
    }
}
