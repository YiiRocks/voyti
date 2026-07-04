<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\User;

use YiiRocks\Voyti\Entity\User;

final readonly class UserEvent
{
    public const string AFTER_ACCOUNT_UPDATE = 'afterAccountUpdate';
    public const string AFTER_BLOCK = 'afterBlock';
    public const string AFTER_CONFIRMATION = 'afterConfirmation';
    public const string AFTER_CREATE = 'afterCreate';
    public const string AFTER_DELETE = 'afterDelete';
    public const string AFTER_LOGOUT = 'afterLogout';
    public const string AFTER_PROFILE_UPDATE = 'afterProfileUpdate';
    public const string AFTER_SWITCH_IDENTITY = 'afterSwitchIdentity';
    public const string AFTER_UNBLOCK = 'afterUnblock';
    public const string BEFORE_ACCOUNT_UPDATE = 'beforeAccountUpdate';
    public const string BEFORE_BLOCK = 'beforeBlock';
    public const string BEFORE_CONFIRMATION = 'beforeConfirmation';
    public const string BEFORE_CREATE = 'beforeCreate';
    public const string BEFORE_DELETE = 'beforeDelete';
    public const string BEFORE_LOGOUT = 'beforeLogout';
    public const string BEFORE_PROFILE_UPDATE = 'beforeProfileUpdate';
    public const string BEFORE_SWITCH_IDENTITY = 'beforeSwitchIdentity';
    public const string BEFORE_UNBLOCK = 'beforeUnblock';

    public function __construct(
        private User $user,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
