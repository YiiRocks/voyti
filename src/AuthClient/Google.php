<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class Google
{

    public function getAuthUrl(): string
    {
        return 'https://accounts.google.com/o/oauth2/auth';
    }
    public function getName(): string
    {
        return 'google';
    }

    public function getScope(): string
    {
        return 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.userProfile';
    }

    public function getTitle(): string
    {
        return 'Google';
    }

    public function getTokenUrl(): string
    {
        return 'https://accounts.google.com/o/oauth2/userToken';
    }
}
