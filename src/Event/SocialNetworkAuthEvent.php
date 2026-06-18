<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event;

use YiiRocks\Voyti\Entity\SocialNetworkAccount;

final class SocialNetworkAuthEvent
{
    public const BEFORE_AUTHENTICATE = 'beforeAuthenticate';
    public const AFTER_AUTHENTICATE = 'afterAuthenticate';
    public const BEFORE_CONNECT = 'beforeConnect';
    public const AFTER_CONNECT = 'afterConnect';

    public function __construct(
        private readonly SocialNetworkAccount $account,
        private readonly \Yiisoft\Auth\Client\ClientInterface $client,
    ) {
    }

    public function getAccount(): SocialNetworkAccount
    {
        return $this->account;
    }

    public function getClient(): \Yiisoft\Auth\Client\ClientInterface
    {
        return $this->client;
    }
}
