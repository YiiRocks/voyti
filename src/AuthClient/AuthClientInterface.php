<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

use YiiRocks\Voyti\Http\ClientInterface;

/**
 * Contract for an OAuth2 social login provider: building the authorization URL and exchanging an
 * authorization code for normalized user attributes.
 */
interface AuthClientInterface
{
    public function fetchUserAttributes(string $code, string $redirectUri, ClientInterface $httpClient): array;

    public function getAuthorizationUrl(string $redirectUri, string $state): string;

    public function getName(): string;

    public function getTitle(): string;

    public function isEnabled(): bool;
}
