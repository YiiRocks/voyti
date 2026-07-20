<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Shared;

use YiiRocks\Voyti\AuthClient\AuthClientInterface;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * The list of "sign in/connect with X" buttons for configured, non-excluded social providers.
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
        AuthClientRegistry $authClients,
        UrlGeneratorInterface $url,
        array $excludedProviders = [],
        string $routeName = 'voyti/session-auth',
    ): self {
        $providers = array_map(
            static fn(AuthClientInterface $client): SocialProviderLink => new SocialProviderLink(
                $client->getTitle(),
                $url->generate($routeName, ['provider' => $client->getName()]),
            ),
            $authClients->allExcept($excludedProviders),
        );

        return new self($providers);
    }
}
