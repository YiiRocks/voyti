<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;

final class ExpireService
{
    public function __construct(
        private readonly ModuleConfig $config,
    ) {
    }

    public function checkPasswordExpiration(User $user): bool
    {
        if (!$this->config->enablePasswordExpiration) {
            return false;
        }
        return $this->isExpired($user);
    }

    public function isExpired(User $user): bool
    {
        if ($this->config->maxPasswordAge === null) {
            return false;
        }
        return $user->getPasswordAge() >= $this->config->maxPasswordAge;
    }

    public function run(User $user): bool
    {
        $user->setPasswordChangedAt(0);
        return $user->save();
    }
}
