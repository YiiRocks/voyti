<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Gdpr;

use YiiRocks\Voyti\Model\User;

final readonly class GdprEvent
{
    public function __construct(
        private User $user,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
