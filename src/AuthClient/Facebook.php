<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class Facebook
{

    public function getAuthUrl(): string
    {
        return 'https://www.facebook.com/dialog/oauth';
    }
    public function getName(): string
    {
        return 'facebook';
    }

    public function getScope(): string
    {
        return 'email';
    }

    public function getTitle(): string
    {
        return 'Facebook';
    }

    public function getTokenUrl(): string
    {
        return 'https://graph.facebook.com/v2.0/oauth/access_token';
    }
}
