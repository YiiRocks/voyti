<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class Keycloak
{
    private string $baseUrl = '';
    private string $realm = '';

    public function __construct(string $baseUrl = '', string $realm = '')
    {
        $this->baseUrl = $baseUrl;
        $this->realm = $realm;
    }

    public function getAuthUrl(): string
    {
        return "{$this->baseUrl}/realms/{$this->realm}/protocol/openid-connect/auth";
    }

    public function getName(): string
    {
        return 'keycloak';
    }

    public function getScope(): string
    {
        return 'openid profile email';
    }

    public function getTitle(): string
    {
        return 'Keycloak';
    }

    public function getTokenUrl(): string
    {
        return "{$this->baseUrl}/realms/{$this->realm}/protocol/openid-connect/token";
    }
}
