<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Password;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\UserPasswordHistory;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\PasswordHistoryService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Security\PasswordHasher;

final class PasswordHistoryServiceTest extends TestCase
{
    use DatabaseSetupTrait;
    use UserFactoryTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testRecordDoesNothingWhenDisabled(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: false);
        $passwordHasher = new PasswordHasher();
        $user = $this->createUser(
            username: 'historyuser',
            email: 'history@example.com',
            passwordHash: $passwordHasher->hash('currentpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);

        $service->record($user);

        self::assertCount(0, UserPasswordHistory::findByUserId($user->getIdOrZero()));
    }

    public function testRecordPrunesBeyondLimit(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true, passwordHistoryLimit: 2);
        $passwordHasher = new PasswordHasher();
        $user = $this->createUser(
            username: 'historyuser',
            email: 'history@example.com',
            passwordHash: $passwordHasher->hash('pass0'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);

        $service->record($user);

        $user->setPasswordHash($passwordHasher->hash('pass1'));
        $user->save();
        $service->record($user);

        $user->setPasswordHash($passwordHasher->hash('pass2'));
        $user->save();
        $service->record($user);

        self::assertCount(2, UserPasswordHistory::findByUserId($user->getIdOrZero()));
    }

    public function testRecordStoresCurrentHash(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true);
        $passwordHasher = new PasswordHasher();
        $user = $this->createUser(
            username: 'historyuser',
            email: 'history@example.com',
            passwordHash: $passwordHasher->hash('currentpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);

        $beforeRecord = time();
        $service->record($user);

        $history = UserPasswordHistory::findByUserId($user->getIdOrZero());
        self::assertCount(1, $history);
        self::assertTrue($passwordHasher->validate('currentpass', $history[0]->getPasswordHash()));
        self::assertGreaterThanOrEqual($beforeRecord, $history[0]->getCreatedAt());
    }

    public function testWasUsedRecentlyChecksHistoryEntries(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true);
        $passwordHasher = new PasswordHasher();
        $user = $this->createUser(
            username: 'historyuser',
            email: 'history@example.com',
            passwordHash: $passwordHasher->hash('originalpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);
        $service->record($user);

        $user->setPasswordHash($passwordHasher->hash('secondpass'));
        $user->save();
        $service->record($user);

        self::assertTrue($service->wasUsedRecently($user, 'originalpass'));
        self::assertTrue($service->wasUsedRecently($user, 'secondpass'));
        self::assertFalse($service->wasUsedRecently($user, 'neverusedpass'));
    }

    public function testWasUsedRecentlyIsFalseWhenDisabled(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: false);
        $passwordHasher = new PasswordHasher();
        $user = $this->createUser(
            username: 'historyuser',
            email: 'history@example.com',
            passwordHash: $passwordHasher->hash('currentpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);

        self::assertFalse($service->wasUsedRecently($user, 'currentpass'));
    }

    public function testWasUsedRecentlyMatchesCurrentHash(): void
    {
        $config = new ModuleConfig(enablePasswordExpiration: true);
        $passwordHasher = new PasswordHasher();
        $user = $this->createUser(
            username: 'historyuser',
            email: 'history@example.com',
            passwordHash: $passwordHasher->hash('currentpass'),
        );
        $service = new PasswordHistoryService($passwordHasher, $config);

        self::assertTrue($service->wasUsedRecently($user, 'currentpass'));
    }
}
