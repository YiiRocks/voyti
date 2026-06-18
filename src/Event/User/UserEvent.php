<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\User;

use YiiRocks\Voyti\Entity\User;

final class UserEvent
{
    public const AFTER_ACCOUNT_UPDATE = 'afterAccountUpdate';
    public const AFTER_BLOCK = 'afterBlock';
    public const AFTER_CONFIRMATION = 'afterConfirmation';
    public const AFTER_CREATE = 'afterCreate';
    public const AFTER_DELETE = 'afterDelete';
    public const AFTER_LOGOUT = 'afterLogout';
    public const AFTER_PROFILE_UPDATE = 'afterProfileUpdate';
    public const AFTER_SWITCH_IDENTITY = 'afterSwitchIdentity';
    public const AFTER_UNBLOCK = 'afterUnblock';
    public const BEFORE_ACCOUNT_UPDATE = 'beforeAccountUpdate';
    public const BEFORE_BLOCK = 'beforeBlock';
    public const BEFORE_CONFIRMATION = 'beforeConfirmation';
    public const BEFORE_CREATE = 'beforeCreate';
    public const BEFORE_DELETE = 'beforeDelete';
    public const BEFORE_LOGOUT = 'beforeLogout';
    public const BEFORE_PROFILE_UPDATE = 'beforeProfileUpdate';
    public const BEFORE_SWITCH_IDENTITY = 'beforeSwitchIdentity';
    public const BEFORE_UNBLOCK = 'beforeUnblock';

    public function __construct(
        private readonly User $user,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
