<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

use YiiRocks\Voyti\ModuleConfig;

final class AuthClientRegistryFactory
{
    public function __construct(
        private readonly ModuleConfig $config,
    ) {
    }

    public function create(): AuthClientRegistry
    {
        $clients = [];

        foreach ($this->config->socialNetworkClients as $provider => $providerConfig) {
            if (!is_string($provider) || !is_array($providerConfig)) {
                continue;
            }

            /** @var array<string, mixed> $providerConfig */
            $client = match ($provider) {
                'facebook' => new Facebook($providerConfig),
                'github' => new GitHub($providerConfig),
                'google' => new Google($providerConfig),
                'keycloak' => new Keycloak($providerConfig),
                'linkedin' => new LinkedIn($providerConfig),
                'microsoft365' => new Microsoft365($providerConfig),
                'twitter' => new Twitter($providerConfig),
                'vkontakte' => new VKontakte($providerConfig),
                'yandex' => new Yandex($providerConfig),
                default => null,
            };

            if ($client instanceof AuthClientInterface && $client->isEnabled()) {
                $clients[] = $client;
            }
        }

        return new AuthClientRegistry(...$clients);
    }
}
