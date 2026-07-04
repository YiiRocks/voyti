<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSocialAccount;

final readonly class UserSocialConnectEvent
{
    public const string AFTER_CONNECT = 'afterConnect';
    public const string AFTER_DISCONNECT = 'afterDisconnect';
    public const string BEFORE_CONNECT = 'beforeConnect';
    public const string BEFORE_DISCONNECT = 'beforeDisconnect';

    public function __construct(
        private User $user,
        private UserSocialAccount $account,
    ) {
    }

    public function getAccount(): UserSocialAccount
    {
        return $this->account;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
