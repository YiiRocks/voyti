<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class GitHub
{
    public function getName(): string
    {
        return 'github';
    }

    public function getTitle(): string
    {
        return 'GitHub';
    }

    public function getAuthUrl(): string
    {
        return 'https://github.com/login/oauth/authorize';
    }

    public function getTokenUrl(): string
    {
        return 'https://github.com/login/oauth/access_token';
    }

    public function getScope(): string
    {
        return 'user:email';
    }
}
