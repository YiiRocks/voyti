<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

use YiiRocks\Voyti\Entity\UserSocialAccount;

final readonly class UserSocialAuthEvent
{
    public const string AFTER_AUTHENTICATE = 'afterAuthenticate';
    public const string AFTER_CONNECT = 'afterConnect';
    public const string BEFORE_AUTHENTICATE = 'beforeAuthenticate';
    public const string BEFORE_CONNECT = 'beforeConnect';

    public function __construct(
        private UserSocialAccount $account,
        private object $client,
    ) {
    }

    public function getAccount(): UserSocialAccount
    {
        return $this->account;
    }

    public function getClient(): object
    {
        return $this->client;
    }
}
