<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class Twitter
{

    public function getAuthUrl(): string
    {
        return 'https://api.twitter.com/oauth/authenticate';
    }
    public function getName(): string
    {
        return 'twitter';
    }

    public function getScope(): string
    {
        return '';
    }

    public function getTitle(): string
    {
        return 'Twitter';
    }

    public function getTokenUrl(): string
    {
        return 'https://api.twitter.com/oauth/access_token';
    }
}
