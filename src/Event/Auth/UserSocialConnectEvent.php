<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserSocialAccount;

final class UserSocialConnectEvent
{
    public const AFTER_CONNECT = 'afterConnect';
    public const AFTER_DISCONNECT = 'afterDisconnect';
    public const BEFORE_CONNECT = 'beforeConnect';
    public const BEFORE_DISCONNECT = 'beforeDisconnect';

    public function __construct(
        private readonly User $user,
        private readonly UserSocialAccount $account,
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
