<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

final readonly class Keycloak extends AbstractAuthClient
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $baseUrl = isset($config['baseUrl']) && is_string($config['baseUrl']) ? rtrim($config['baseUrl'], '/') : '';
        $realm = isset($config['realm']) && is_string($config['realm']) ? trim($config['realm']) : '';

        parent::__construct(
            'keycloak',
            'Keycloak',
            "{$baseUrl}/realms/{$realm}/protocol/openid-connect/auth",
            "{$baseUrl}/realms/{$realm}/protocol/openid-connect/token",
            "{$baseUrl}/realms/{$realm}/protocol/openid-connect/userinfo",
            'openid email profile',
            $config,
        );
    }
}
