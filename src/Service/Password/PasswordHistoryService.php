<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserPasswordHistory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Security\PasswordHasher;

/**
 * Reuse prevention only matters if passwords are ever forced to change, so this piggybacks on
 * `VoytiConfig::$maxPasswordAge` rather than exposing a separate toggle - there is no config to
 * "disallow reusing your password" without also enforcing periodic changes.
 */
final readonly class PasswordHistoryService
{
    public function __construct(
        private PasswordHasher $passwordHasher,
        private VoytiConfig $config,
    ) {}

    public function applyPasswordChange(User $user, string $plainPassword): void
    {
        $user->setPasswordHash($this->passwordHasher->hash($plainPassword));
        $user->setPasswordChangedAt(time());
        $user->setUpdatedAt(time());
        $user->save();
        $this->record($user);
    }

    /**
     * Records the user's current password hash into their history. Call this after the new hash
     * has already been set and saved on $user.
     */
    public function record(User $user): void
    {
        if ($this->config->maxPasswordAge <= 0) {
            return;
        }

        $userId = $user->getIdOrZero();

        $history = new UserPasswordHistory();
        $history->setUserId($userId);
        $history->setPasswordHash($user->getPasswordHash());
        $history->setCreatedAt(time());
        $history->save();

        $this->pruneOldHistory($userId);
    }

    public function wasUsedRecently(User $user, string $plainPassword): bool
    {
        if ($this->config->maxPasswordAge <= 0) {
            return false;
        }

        if ($this->passwordHasher->validate($plainPassword, $user->getPasswordHash())) {
            return true;
        }

        foreach (UserPasswordHistory::findByUserId($user->getIdOrZero()) as $entry) {
            if ($this->passwordHasher->validate($plainPassword, $entry->getPasswordHash())) {
                return true;
            }
        }

        return false;
    }

    private function pruneOldHistory(int $userId): void
    {
        $history = UserPasswordHistory::findByUserId($userId);
        $limit = $this->config->passwordHistoryLimit;

        /** @var list<UserPasswordHistory> $toDelete */
        $toDelete = array_slice($history, $limit);
        $hashesToDelete = array_map(
            static fn(UserPasswordHistory $entry): string => $entry->getPasswordHash(),
            $toDelete,
        );
        (new UserPasswordHistory())->deleteAll(['user_id' => $userId, 'password_hash' => $hashesToDelete]);
    }
}
