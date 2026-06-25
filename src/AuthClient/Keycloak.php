<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final class Keycloak extends AbstractAuthClient
{
    public function __construct(
        string $baseUrl = '',
        string $realm = '',
    ) {
        parent::__construct(
            "{$baseUrl}/realms/{$realm}/protocol/openid-connect/auth",
            'keycloak',
            'openid userProfile email',
            'Keycloak',
            "{$baseUrl}/realms/{$realm}/protocol/openid-connect/userToken",
        );
    }
}
