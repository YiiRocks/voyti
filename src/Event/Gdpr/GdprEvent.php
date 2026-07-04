<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Gdpr;

use YiiRocks\Voyti\Entity\User;

final class GdprEvent
{
    public const string AFTER_DELETE = 'afterDelete';
    public const string BEFORE_DELETE = 'beforeDelete';

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
