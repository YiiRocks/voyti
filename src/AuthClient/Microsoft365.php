<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class Microsoft365
{
    public function getName(): string
    {
        return 'microsoft365';
    }

    public function getTitle(): string
    {
        return 'Microsoft 365';
    }

    public function getAuthUrl(): string
    {
        return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    }

    public function getTokenUrl(): string
    {
        return 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    }

    public function getScope(): string
    {
        return 'openid profile email';
    }
}
