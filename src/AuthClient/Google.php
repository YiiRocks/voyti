<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class Google
{
    public function getName(): string
    {
        return 'google';
    }

    public function getTitle(): string
    {
        return 'Google';
    }

    public function getAuthUrl(): string
    {
        return 'https://accounts.google.com/o/oauth2/auth';
    }

    public function getTokenUrl(): string
    {
        return 'https://accounts.google.com/o/oauth2/token';
    }

    public function getScope(): string
    {
        return 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile';
    }
}
