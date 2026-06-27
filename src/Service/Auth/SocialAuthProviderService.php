<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Auth;

use RuntimeException;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\AuthClient\OAuthHttpClientInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Security\Random;
use Yiisoft\Session\SessionInterface;

final class SocialAuthProviderService
{
    public function __construct(
        private readonly AuthClientRegistry $authClientRegistry,
        private readonly OAuthHttpClientInterface $httpClient,
        private readonly SessionInterface $session,
        private readonly UrlGeneratorInterface $url,
    ) {
    }

    public function begin(string $provider, string $routeName): string
    {
        $client = $this->client($provider);
        $state = Random::string(32);
        $this->session->set($this->stateKey($provider, $routeName), $state);

        return $client->getAuthorizationUrl($this->redirectUri($provider, $routeName), $state);
    }

    /**
     * @param array<string, mixed> $queryParams
     *
     * @return array
     */
    public function complete(string $provider, string $routeName, array $queryParams): array
    {
        $client = $this->client($provider);
        $storedState = $this->session->get($this->stateKey($provider, $routeName));
        $this->session->remove($this->stateKey($provider, $routeName));

        $error = $queryParams['error_description'] ?? $queryParams['error'] ?? null;
        if (is_string($error) && $error !== '') {
            throw new RuntimeException($error);
        }

        $state = $queryParams['state'] ?? null;
        if (!is_string($state) || $state === '' || !is_string($storedState) || $storedState === '' || $state !== $storedState) {
            throw new RuntimeException('The social authentication state is invalid or expired.');
        }

        $code = $queryParams['code'] ?? null;
        if (!is_string($code) || $code === '') {
            throw new RuntimeException('The social authentication code is missing.');
        }

        return $client->fetchUserAttributes($code, $this->redirectUri($provider, $routeName), $this->httpClient);
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    public function hasCallbackParameters(array $queryParams): bool
    {
        foreach (['code', 'state', 'error', 'error_description'] as $key) {
            if (isset($queryParams[$key])) {
                return true;
            }
        }

        return false;
    }

    private function client(string $provider): \YiiRocks\Voyti\AuthClient\AuthClientInterface
    {
        $client = $this->authClientRegistry->get($provider);
        if ($client === null) {
            throw new RuntimeException("The '{$provider}' social provider is not configured.");
        }

        return $client;
    }

    private function redirectUri(string $provider, string $routeName): string
    {
        return $this->url->generateAbsolute($routeName, ['provider' => $provider]);
    }

    private function stateKey(string $provider, string $routeName): string
    {
        return 'voyti.social_auth.state.' . md5($routeName . ':' . $provider);
    }
}
