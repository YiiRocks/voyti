<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserPasswordHistory;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Security\PasswordHasher;

/**
 * Reuse prevention only matters if passwords are ever forced to change, so this piggybacks on
 * enablePasswordExpiration rather than exposing a separate toggle - there is no config to
 * "disallow reusing your password" without also enforcing periodic changes.
 */
final readonly class PasswordHistoryService
{
    public function __construct(
        private PasswordHasher $passwordHasher,
        private ModuleConfig $config,
    ) {
    }

    /**
     * Records the user's current password hash into their history. Call this after the new hash
     * has already been set and saved on $user.
     */
    public function record(User $user): void
    {
        if (!$this->config->enablePasswordExpiration) {
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
        if (!$this->config->enablePasswordExpiration) {
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
        foreach ($toDelete as $entry) {
            $entry->delete();
        }
    }
}
