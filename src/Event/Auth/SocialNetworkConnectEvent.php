<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

use YiiRocks\Voyti\Entity\SocialNetworkAccount;
use YiiRocks\Voyti\Entity\User;

final class SocialNetworkConnectEvent
{
    public const AFTER_CONNECT = 'afterConnect';
    public const AFTER_DISCONNECT = 'afterDisconnect';
    public const BEFORE_CONNECT = 'beforeConnect';
    public const BEFORE_DISCONNECT = 'beforeDisconnect';

    public function __construct(
        private readonly User $user,
        private readonly SocialNetworkAccount $account,
    ) {
    }

    public function getAccount(): SocialNetworkAccount
    {
        return $this->account;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
