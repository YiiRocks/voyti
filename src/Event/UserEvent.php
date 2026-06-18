<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event;

use YiiRocks\Voyti\Entity\User;

final class UserEvent
{
    public const BEFORE_CREATE = 'beforeCreate';
    public const AFTER_CREATE = 'afterCreate';
    public const BEFORE_DELETE = 'beforeDelete';
    public const AFTER_DELETE = 'afterDelete';
    public const BEFORE_ACCOUNT_UPDATE = 'beforeAccountUpdate';
    public const AFTER_ACCOUNT_UPDATE = 'afterAccountUpdate';
    public const BEFORE_PROFILE_UPDATE = 'beforeProfileUpdate';
    public const AFTER_PROFILE_UPDATE = 'afterProfileUpdate';
    public const BEFORE_CONFIRMATION = 'beforeConfirmation';
    public const AFTER_CONFIRMATION = 'afterConfirmation';
    public const BEFORE_UNBLOCK = 'beforeUnblock';
    public const AFTER_UNBLOCK = 'afterUnblock';
    public const BEFORE_BLOCK = 'beforeBlock';
    public const AFTER_BLOCK = 'afterBlock';
    public const BEFORE_LOGOUT = 'beforeLogout';
    public const AFTER_LOGOUT = 'afterLogout';
    public const BEFORE_SWITCH_IDENTITY = 'beforeSwitchIdentity';
    public const AFTER_SWITCH_IDENTITY = 'afterSwitchIdentity';

    public function __construct(
        private readonly User $user,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
