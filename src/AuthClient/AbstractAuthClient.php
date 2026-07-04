<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

use RuntimeException;
use YiiRocks\Voyti\Http\ClientInterface;

abstract readonly class AbstractAuthClient implements AuthClientInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private string $name,
        private string $title,
        private string $authUrl,
        private string $tokenUrl,
        private string $userInfoUrl,
        private string $scope = '',
        private array $config = [],
    ) {
    }

    /**
     * @return array
     *
     * @psalm-return array<string, mixed>
     */
    #[\Override]
    public function fetchUserAttributes(string $code, string $redirectUri, ClientInterface $httpClient): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException("The '{$this->name}' social provider is disabled.");
        }

        $tokenData = $this->exchangeCodeForToken($code, $redirectUri, $httpClient);
        $attributes = $this->loadUserAttributes($tokenData, $httpClient);
        $normalized = $this->normalizeUserAttributes($attributes, $tokenData);
        $id = $normalized['id'] ?? null;

        if (!is_string($id) || $id === '') {
            throw new RuntimeException("Unable to determine the '{$this->name}' account identifier.");
        }

        return $normalized;
    }

    #[\Override]
    public function getAuthorizationUrl(string $redirectUri, string $state): string
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException("The '{$this->name}' social provider is disabled.");
        }

        return $this->appendQuery(
            $this->authUrl,
            array_merge(
                [
                    'client_id' => $this->clientId(),
                    'redirect_uri' => $this->resolveRedirectUri($redirectUri),
                    'response_type' => 'code',
                    'scope' => $this->scope(),
                    'state' => $state,
                ],
                $this->authorizationParameters(),
            ),
        );
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[\Override]
    public function getTitle(): string
    {
        return $this->title;
    }

    #[\Override]
    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    /**
     * @return array<string, string>
     */
    protected function authorizationParameters(): array
    {
        $parameters = $this->config['authorizationParams'] ?? [];

        return is_array($parameters) ? $this->stringMap($parameters) : [];
    }

    protected function clientId(): string
    {
        $clientId = $this->config['clientId'] ?? '';

        if (!is_string($clientId) || $clientId === '') {
            throw new RuntimeException("The '{$this->name}' clientId is not configured.");
        }

        return $clientId;
    }

    protected function clientSecret(): string
    {
        $clientSecret = $this->config['clientSecret'] ?? '';

        if (!is_string($clientSecret) || $clientSecret === '') {
            throw new RuntimeException("The '{$this->name}' clientSecret is not configured.");
        }

        return $clientSecret;
    }

    /**
     * @param array $data
     * @param list<string> $keys
     *
     * @return null|string
     */
    protected function firstString(array $data, array $keys): string|null
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $tokenData
     */
    protected function identifier(array $attributes, array $tokenData): string
    {
        $id = $this->firstString($attributes, ['id', 'sub', 'user_id']) ?? $this->firstString($tokenData, ['user_id']);

        return $id ?? '';
    }

    /**
     * @param array<string, mixed> $tokenData
     *
     * @return array
     *
     * @psalm-return array<string, mixed>
     */
    protected function loadUserAttributes(array $tokenData, ClientInterface $httpClient): array
    {
        return $httpClient->send(
            'GET',
            $this->userInfoUrl,
            $this->userInfoHeaders($tokenData),
            $this->userInfoQuery($tokenData),
        );
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $tokenData
     *
     * @return (null|string)[]
     *
     * @psalm-return array{id: string, email: null|string, username: null|string, name: null|string}
     */
    protected function normalizeUserAttributes(array $attributes, array $tokenData): array
    {
        $email = $this->firstString($attributes, ['email', 'default_email']);
        $username = $this->firstString(
            $attributes,
            ['preferred_username', 'login', 'username', 'screen_name', 'display_name', 'nickname', 'name'],
        );
        $name = $this->firstString($attributes, ['name', 'real_name', 'display_name']);

        if ($name === null) {
            $firstName = $this->firstString($attributes, ['first_name', 'given_name']);
            $lastName = $this->firstString($attributes, ['last_name', 'family_name']);
            $name = trim(implode(' ', [$firstName, $lastName]));
            $name = $name !== '' ? $name : null;
        }

        if ($username === null && $email !== null) {
            $username = strstr($email, '@', true) ?: $email;
        }

        return [
            'id' => $this->identifier($attributes, $tokenData),
            'email' => $email,
            'username' => $username,
            'name' => $name,
        ];
    }

    protected function resolveRedirectUri(string $fallback): string
    {
        $redirectUri = $this->config['redirectUri'] ?? $fallback;

        if (!is_string($redirectUri) || $redirectUri === '') {
            throw new RuntimeException("The '{$this->name}' redirect URI is not configured.");
        }

        return $redirectUri;
    }

    protected function scope(): string
    {
        $scope = $this->config['scope'] ?? $this->scope;

        return is_string($scope) ? $scope : $this->scope;
    }

    /**
     * @param array<string, mixed> $tokenData
     *
     * @return string[]
     *
     * @psalm-return array<string, string>
     */
    protected function userInfoHeaders(array $tokenData): array
    {
        $token = $this->accessToken($tokenData);

        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => 'YiiRocks Voyti',
        ];
    }

    /**
     * @param array<string, mixed> $tokenData
     *
     * @return array<string, string>
     */
    protected function userInfoQuery(array $tokenData): array
    {
        $query = $this->config['userInfoQuery'] ?? [];

        return is_array($query) ? $this->stringMap($query) : [];
    }

    /**
     * @param array<string, mixed> $tokenData
     */
    private function accessToken(array $tokenData): string
    {
        $token = $tokenData['access_token'] ?? null;

        if (!is_string($token) || $token === '') {
            throw new RuntimeException("The '{$this->name}' access token is missing.");
        }

        return $token;
    }

    private function appendQuery(string $url, array $query): string
    {
        $query = array_filter(
            $query,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        $queryString = http_build_query($query);
        if ($queryString === '') {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . $queryString;
    }

    /**
     * @param array<string, mixed> $tokenData
     *
     * @psalm-return array<string, mixed>
     */
    private function exchangeCodeForToken(string $code, string $redirectUri, ClientInterface $httpClient): array
    {
        return $httpClient->send(
            'POST',
            $this->tokenUrl,
            [
                'Accept' => 'application/json',
                'User-Agent' => 'YiiRocks Voyti',
            ],
            [],
            $this->tokenRequestBody($code, $redirectUri),
        );
    }

    /**
     * @param array $values
     *
     * @return string[]
     *
     * @psalm-return array<string, string>
     */
    private function stringMap(array $values): array
    {
        $mapped = [];

        foreach ($values as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
                $mapped[$key] = (string) $value;
            }
        }

        return $mapped;
    }

    /**
     * @return string[]
     *
     * @psalm-return array<string, string>
     */
    private function tokenRequestBody(string $code, string $redirectUri): array
    {
        $body = [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->resolveRedirectUri($redirectUri),
        ];

        $tokenParameters = $this->config['tokenParams'] ?? [];

        return is_array($tokenParameters)
            ? array_merge($body, $this->stringMap($tokenParameters))
            : $body;
    }
}
