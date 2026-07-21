<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\AuthClient;

use Override;
use RuntimeException;
use YiiRocks\Voyti\Http\ClientInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Method;

/**
 * Base OAuth2 authorization-code-flow implementation shared by all social auth providers: builds the
 * authorization URL, exchanges the code for a token, fetches and normalizes user attributes. Subclasses
 * override the `protected` hooks (`normalizeUserAttributes()`, `userInfoHeaders()`, `userInfoQuery()`,
 * `loadUserAttributes()`) to accommodate provider-specific request/response shapes.
 */
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
    ) {}

    /**
     * @return array
     *
     * @psalm-return array<string, mixed>
     */
    #[Override]
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

    #[Override]
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

    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[Override]
    public function getTitle(): string
    {
        return $this->title;
    }

    #[Override]
    public function isEnabled(): bool
    {
        /** @var mixed $rawEnabled */
        $rawEnabled = $this->config['enabled'] ?? true;

        return (bool) $rawEnabled;
    }

    /**
     * @return array<string, string>
     */
    protected function authorizationParameters(): array
    {
        /** @var mixed $params */
        $params = $this->config['authorizationParams'] ?? [];

        return $this->stringMap(is_array($params) ? $params : []);
    }

    protected function clientId(): string
    {
        /** @var mixed $rawClientId */
        $rawClientId = $this->config['clientId'] ?? '';

        if (!is_string($rawClientId) || $rawClientId === '') {
            throw new RuntimeException("The '{$this->name}' clientId is not configured.");
        }

        return $rawClientId;
    }

    protected function clientSecret(): string
    {
        /** @var mixed $rawClientSecret */
        $rawClientSecret = $this->config['clientSecret'] ?? '';

        if (!is_string($rawClientSecret) || $rawClientSecret === '') {
            throw new RuntimeException("The '{$this->name}' clientSecret is not configured.");
        }

        return $rawClientSecret;
    }

    /**
     * @param array $data
     * @param list<string> $keys
     *
     * @return null|string
     */
    protected function firstString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            if (is_string($data[$key]) && $data[$key] !== '') {
                return $data[$key];
            }
            if (is_int($data[$key]) || is_float($data[$key])) {
                return (string) $data[$key];
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
            Method::GET,
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
            $prefix = strstr($email, '@', true);
            $username = is_string($prefix) && $prefix !== '' ? $prefix : $email;
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
        /** @var mixed $rawRedirectUri */
        $rawRedirectUri = $this->config['redirectUri'] ?? $fallback;

        if (!is_string($rawRedirectUri) || $rawRedirectUri === '') {
            throw new RuntimeException("The '{$this->name}' redirect URI is not configured.");
        }

        return $rawRedirectUri;
    }

    protected function scope(): string
    {
        /** @var mixed $configScope */
        $configScope = $this->config['scope'] ?? null;

        return is_string($configScope) ? $configScope : $this->scope;
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
            Header::AUTHORIZATION => 'Bearer ' . $token,
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
        /** @var mixed $query */
        $query = $this->config['userInfoQuery'] ?? [];

        return $this->stringMap(is_array($query) ? $query : []);
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
            static fn(mixed $value): bool => $value !== null && $value !== '',
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
            Method::POST,
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

        array_walk(
            $values,
            function (mixed $value, mixed $key) use (&$mapped): void {
                if (!is_string($key) || $key === '') {
                    return;
                }
                if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
                    $mapped[$key] = (string) $value;
                }
            },
        );

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

        /** @var mixed $tokenParams */
        $tokenParams = $this->config['tokenParams'] ?? [];

        return is_array($tokenParams)
            ? array_merge($body, $this->stringMap($tokenParams))
            : $body;
    }
}
