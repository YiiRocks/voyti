<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event;

use YiiRocks\Voyti\Entity\SocialNetworkAccount;
use YiiRocks\Voyti\Entity\User;

final class SocialNetworkConnectEvent
{
    public const BEFORE_CONNECT = 'beforeConnect';
    public const AFTER_CONNECT = 'afterConnect';
    public const BEFORE_DISCONNECT = 'beforeDisconnect';
    public const AFTER_DISCONNECT = 'afterDisconnect';

    public function __construct(
        private readonly User $user,
        private readonly SocialNetworkAccount $account,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getAccount(): SocialNetworkAccount
    {
        return $this->account;
    }
}
