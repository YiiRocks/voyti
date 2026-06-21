<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Auth;

use YiiRocks\Voyti\Entity\UserSocialAccount;

final class UserSocialAuthEvent
{
    public const AFTER_AUTHENTICATE = 'afterAuthenticate';
    public const AFTER_CONNECT = 'afterConnect';
    public const BEFORE_AUTHENTICATE = 'beforeAuthenticate';
    public const BEFORE_CONNECT = 'beforeConnect';

    public function __construct(
        private readonly UserSocialAccount $account,
        private readonly \Yiisoft\Auth\Client\ClientInterface $client,
    ) {
    }

    public function getAccount(): UserSocialAccount
    {
        return $this->account;
    }

    public function getClient(): \Yiisoft\Auth\Client\ClientInterface
    {
        return $this->client;
    }
}
