<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\TwoFactor;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\UserBackupCode;
use YiiRocks\Voyti\Service\TwoFactor\BackupCodeService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use Yiisoft\Security\PasswordHasher;

final class BackupCodeServiceTest extends TestCase
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

    public function testClearRemovesAllCodesIncludingUsedOnes(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(new PasswordHasher());
        $codes = $service->generate($user);
        $service->consume($user, $codes[0]);

        $service->clear($user);

        self::assertFalse($service->hasUnused($user));
        self::assertCount(0, UserBackupCode::query()->where(['user_id' => $user->getIdOrZero()])->all());
    }

    public function testConsumeFailsForBlankCode(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(new PasswordHasher());
        $service->generate($user);

        self::assertFalse($service->consume($user, ''));
    }

    public function testConsumeFailsForUnknownCode(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(new PasswordHasher());
        $service->generate($user);

        self::assertFalse($service->consume($user, 'not-a-real-code'));
    }

    public function testConsumeMarksCodeAsUsedAndSucceeds(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(new PasswordHasher());
        $codes = $service->generate($user);

        self::assertTrue($service->consume($user, $codes[0]));

        $remainingUnused = UserBackupCode::findUnusedByUserId($user->getIdOrZero());
        self::assertCount(count($codes) - 1, $remainingUnused);
    }

    public function testConsumeRejectsAlreadyUsedCode(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(new PasswordHasher());
        $codes = $service->generate($user);

        self::assertTrue($service->consume($user, $codes[0]));
        self::assertFalse($service->consume($user, $codes[0]));
    }

    public function testGenerateProducesRequestedCountOfUniqueUnusedCodes(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(new PasswordHasher());

        $codes = $service->generate($user, 5);

        self::assertCount(5, $codes);
        self::assertCount(5, array_unique($codes));
        self::assertCount(5, UserBackupCode::findUnusedByUserId($user->getIdOrZero()));
    }

    public function testGenerateReplacesExistingCodes(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(new PasswordHasher());

        $firstBatch = $service->generate($user, 3);
        $service->generate($user, 3);

        foreach ($firstBatch as $code) {
            self::assertFalse($service->consume($user, $code));
        }
    }

    public function testHasUnusedIsFalseWithNoCodes(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(new PasswordHasher());

        self::assertFalse($service->hasUnused($user));
    }

    public function testHasUnusedIsTrueAfterGenerate(): void
    {
        $user = $this->createUser(username: 'backupcodeuser', email: 'backupcode@example.com');
        $service = new BackupCodeService(new PasswordHasher());
        $service->generate($user);

        self::assertTrue($service->hasUnused($user));
    }

}
