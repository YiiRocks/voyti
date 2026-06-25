<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class Twitter extends AbstractAuthClient
{
    public function __construct()
    {
        parent::__construct(
            'https://api.twitter.com/oauth/authenticate',
            'twitter',
            '',
            'Twitter',
            'https://api.twitter.com/oauth/access_token',
        );
    }
}
