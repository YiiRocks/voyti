<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Gdpr;

use YiiRocks\Voyti\Model\User;

final class GdprEvent
{
    public bool $isValid = true;

    public function __construct(
        private readonly User $user,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
