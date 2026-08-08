<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\TwoFactor;

use YiiRocks\Voyti\Model\UserBackupCode;
use YiiRocks\Voyti\Service\TwoFactor\BackupCodeService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;

final class BackupCodeServiceTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public function testClearRemovesAllCodesIncludingUsedOnes(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(TestPasswordHasherFactory::create());
        $codes = $service->generate($user);
        $service->consume($user, $codes[0]);

        $service->clear($user);

        self::assertFalse($service->hasUnused($user));
        self::assertCount(0, UserBackupCode::query()->where(['user_id' => $user->getIdOrZero()])->all());
    }

    public function testConsumeFailsForBlankCode(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(TestPasswordHasherFactory::create());
        $service->generate($user);

        self::assertFalse($service->consume($user, ''));
    }

    public function testConsumeFailsForUnknownCode(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(TestPasswordHasherFactory::create());
        $service->generate($user);

        self::assertFalse($service->consume($user, 'not-a-real-code'));
    }

    public function testConsumeMarksCodeAsUsedAndSucceeds(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(TestPasswordHasherFactory::create());
        $codes = $service->generate($user);

        self::assertTrue($service->consume($user, $codes[0]));

        $remainingUnused = UserBackupCode::findUnusedByUserId($user->getIdOrZero());
        self::assertCount(count($codes) - 1, $remainingUnused);
    }

    public function testGenerateProducesRequestedCountOfUniqueUnusedCodes(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(TestPasswordHasherFactory::create());

        $codes = $service->generate($user, 5);

        self::assertCount(5, $codes);
        self::assertCount(5, array_unique($codes));
        foreach ($codes as $code) {
            self::assertSame(10, strlen($code));
            // Codes are upper-cased; across 50 random characters a dropped strtoupper would leave lowercase.
            self::assertSame(strtoupper($code), $code);
        }
        $stored = UserBackupCode::findUnusedByUserId($user->getIdOrZero());
        self::assertCount(5, $stored);
        // Each persisted code carries a creation timestamp.
        self::assertGreaterThan(0, $stored[0]->getCreatedAt());
    }

    public function testGenerateReplacesExistingCodes(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(TestPasswordHasherFactory::create());

        $firstBatch = $service->generate($user, 3);
        $service->generate($user, 3);

        foreach ($firstBatch as $code) {
            self::assertFalse($service->consume($user, $code));
        }
    }
}
