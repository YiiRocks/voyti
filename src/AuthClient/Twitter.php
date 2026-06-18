<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class Twitter
{
    public function getName(): string
    {
        return 'twitter';
    }

    public function getTitle(): string
    {
        return 'Twitter';
    }

    public function getAuthUrl(): string
    {
        return 'https://api.twitter.com/oauth/authenticate';
    }

    public function getTokenUrl(): string
    {
        return 'https://api.twitter.com/oauth/access_token';
    }

    public function getScope(): string
    {
        return '';
    }
}
