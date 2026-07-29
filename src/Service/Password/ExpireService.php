<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\VoytiConfig;

/**
 * Checks whether a user's password has exceeded {@see VoytiConfig::$maxPasswordAge} and resets the
 * password-changed timestamp to force expiration.
 */
final readonly class ExpireService
{
    public function __construct(
        private VoytiConfig $config,
    ) {}

    public function isExpired(User $user): bool
    {
        if ($this->config->maxPasswordAge <= 0) {
            return false;
        }
        return $user->getPasswordAge() >= $this->config->maxPasswordAge;
    }

    public function run(User $user): bool
    {
        $user->setPasswordChangedAt(0);
        $user->save();
        return true;
    }
}
