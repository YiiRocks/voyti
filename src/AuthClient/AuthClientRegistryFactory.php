<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

use YiiRocks\Voyti\ModuleConfig;

final readonly class AuthClientRegistryFactory
{
    /**
     * @var array<string, array{string, string, string, string, string}>
     */
    private const array GENERIC_PROVIDERS = [
        'google' => ['Google', 'https://accounts.google.com/o/oauth2/v2/auth', 'https://oauth2.googleapis.com/token', 'https://openidconnect.googleapis.com/v1/userinfo', 'openid email profile'],
        'linkedin' => ['LinkedIn', 'https://www.linkedin.com/oauth/v2/authorization', 'https://www.linkedin.com/oauth/v2/accessToken', 'https://api.linkedin.com/v2/userinfo', 'openid profile email'],
        'microsoft365' => ['Microsoft 365', 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize', 'https://login.microsoftonline.com/common/oauth2/v2.0/token', 'https://graph.microsoft.com/oidc/userinfo', 'openid profile email User.Read'],
    ];

    public function __construct(
        private ModuleConfig $config,
    ) {
    }

    public function create(): AuthClientRegistry
    {
        $clients = [];

        foreach ($this->config->socialNetworkClients as $provider => $providerConfig) {
            $client = $this->makeClient($provider, $providerConfig);

            if ($client instanceof AuthClientInterface && $client->isEnabled()) {
                $clients[] = $client;
            }
        }

        return new AuthClientRegistry(...$clients);
    }

    /**
     * @param array<string, mixed> $providerConfig
     */
    private function makeClient(string $provider, array $providerConfig): ?AuthClientInterface
    {
        if ($provider === 'keycloak') {
            return $this->makeKeycloakClient($providerConfig);
        }

        if (isset(self::GENERIC_PROVIDERS[$provider])) {
            [$title, $authUrl, $tokenUrl, $userInfoUrl, $scope] = self::GENERIC_PROVIDERS[$provider];

            return new GenericAuthClient($provider, $title, $authUrl, $tokenUrl, $userInfoUrl, $scope, $providerConfig);
        }

        return match ($provider) {
            'facebook' => new Facebook($providerConfig),
            'github' => new GitHub($providerConfig),
            'vkontakte' => new VKontakte($providerConfig),
            'x' => new Twitter($providerConfig),
            'yandex' => new Yandex($providerConfig),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $providerConfig
     */
    private function makeKeycloakClient(array $providerConfig): GenericAuthClient
    {
        $baseUrl = isset($providerConfig['baseUrl']) && is_string($providerConfig['baseUrl'])
            ? rtrim($providerConfig['baseUrl'], '/')
            : '';
        $realm = isset($providerConfig['realm']) && is_string($providerConfig['realm'])
            ? trim($providerConfig['realm'])
            : '';

        return new GenericAuthClient(
            'keycloak',
            'Keycloak',
            "{$baseUrl}/realms/{$realm}/protocol/openid-connect/auth",
            "{$baseUrl}/realms/{$realm}/protocol/openid-connect/token",
            "{$baseUrl}/realms/{$realm}/protocol/openid-connect/userinfo",
            'openid email profile',
            $providerConfig,
        );
    }
}
