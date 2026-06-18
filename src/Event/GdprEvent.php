<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event;

use YiiRocks\Voyti\Entity\User;

final class GdprEvent
{
    public const BEFORE_DELETE = 'beforeDelete';
    public const AFTER_DELETE = 'afterDelete';

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
