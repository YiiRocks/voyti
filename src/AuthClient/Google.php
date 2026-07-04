<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final readonly class Google extends AbstractAuthClient
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct(
            'google',
            'Google',
            'https://accounts.google.com/o/oauth2/v2/auth',
            'https://oauth2.googleapis.com/token',
            'https://openidconnect.googleapis.com/v1/userinfo',
            'openid email profile',
            $config,
        );
    }
}
