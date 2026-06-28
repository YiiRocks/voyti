<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

use YiiRocks\Voyti\Http\ClientInterface;

interface AuthClientInterface
{
    public function fetchUserAttributes(string $code, string $redirectUri, ClientInterface $httpClient): array;

    public function getName(): string;

    public function getAuthorizationUrl(string $redirectUri, string $state): string;

    public function getTitle(): string;

    public function isEnabled(): bool;
}
