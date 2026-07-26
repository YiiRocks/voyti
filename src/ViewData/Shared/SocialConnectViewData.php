<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Shared;

use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\AuthClient\Collection;

/**
 * The list of "sign in/connect with X" buttons for configured, non-excluded social providers.
 * Empty when `$clientCollection` is `null` - `yiisoft/yii-auth-client` (an optional dependency)
 * isn't installed, see {@see \YiiRocks\Voyti\Service\Auth\SocialAuthClientCollectionFactory}.
 */
final readonly class SocialConnectViewData
{
    /**
     * @param list<SocialProviderLink> $providers
     */
    private function __construct(
        public array $providers,
    ) {}

    /**
     * @param list<string> $excludedProviders
     */
    public static function create(
        ?Collection $clientCollection,
        UrlGeneratorInterface $url,
        array $excludedProviders = [],
        string $routeName = 'voyti/session-auth',
    ): self {
        $providers = [];
        foreach ($clientCollection?->getClients() ?? [] as $provider => $client) {
            if (in_array($provider, $excludedProviders, true)) {
                continue;
            }

            $providers[] = new SocialProviderLink(
                $client->getTitle(),
                $url->generate($routeName, ['authclient' => $provider]),
            );
        }

        return new self($providers);
    }

    public static function providerTitle(?Collection $clientCollection, string $provider): string
    {
        return $clientCollection?->hasClient($provider) === true
            ? $clientCollection->getClient($provider)->getTitle()
            : $provider;
    }
}
