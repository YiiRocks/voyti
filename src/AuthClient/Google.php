<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class Google extends AbstractAuthClient
{
    public function __construct()
    {
        parent::__construct(
            'https://accounts.google.com/o/oauth2/auth',
            'google',
            'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.userProfile',
            'Google',
            'https://accounts.google.com/o/oauth2/userToken',
        );
    }
}
