<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\TwoFactor;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserBackupCode;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;

final readonly class BackupCodeService
{
    public function __construct(
        private PasswordHasher $passwordHasher,
    ) {
    }

    public function clear(User $user): void
    {
        UserBackupCode::deleteAllByUserId($user->getIdOrZero());
    }

    public function consume(User $user, string $code): bool
    {
        if ($code === '') {
            return false;
        }

        foreach (UserBackupCode::findUnusedByUserId($user->getIdOrZero()) as $backupCode) {
            if ($this->passwordHasher->validate($code, $backupCode->getCodeHash())) {
                $backupCode->setUsedAt(time());
                $backupCode->save();
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function generate(User $user, int $count = 10): array
    {
        $userId = $user->getIdOrZero();
        $this->clear($user);

        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Random::string(10));
        }

        foreach ($codes as $code) {
            $backupCode = new UserBackupCode();
            $backupCode->setUserId($userId);
            $backupCode->setCodeHash($this->passwordHasher->hash($code));
            $backupCode->setCreatedAt(time());
            $backupCode->save();
        }

        return $codes;
    }

    public function hasUnused(User $user): bool
    {
        return UserBackupCode::findUnusedByUserId($user->getIdOrZero()) !== [];
    }
}
