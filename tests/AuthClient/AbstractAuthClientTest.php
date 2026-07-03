<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use YiiRocks\Voyti\AuthClient\AbstractAuthClient;
use YiiRocks\Voyti\Http\ClientInterface;

final class AbstractAuthClientTest extends TestCase
{

    public function testAuthorizationParametersIgnoreNonArrayConfig(): void
    {
        $client = new TestAuthClient([
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
            'authorizationParams' => 'invalid',
        ]);

        self::assertSame([], $client->exposedAuthorizationParameters());
    }

    public function testAuthorizationParametersStringifyAllowedScalarValuesOnly(): void
    {
        $client = new TestAuthClient([
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
            'authorizationParams' => [
                '' => 'skip-empty-key',
                0 => 'skip-int-key',
                'count' => 5,
                'ratio' => 2.5,
                'enabled' => true,
                'nested' => ['skip-array-value'],
            ],
        ]);

        self::assertSame(
            [
                'count' => '5',
                'ratio' => '2.5',
                'enabled' => '1',
            ],
            $client->exposedAuthorizationParameters(),
        );
    }

    public function testAuthorizationUrlSkipsEmptyAuthorizationParameterValues(): void
    {
        $client = new TestAuthClient([
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
            'authorizationParams' => [
                'prompt' => 'login',
                'empty' => '',
                'missing' => null,
            ],
        ]);

        $url = $client->getAuthorizationUrl('https://app.example.test/callback', 'state-123');
        $parts = parse_url($url);

        self::assertIsArray($parts);
        parse_str($parts['query'] ?? '', $query);
        self::assertSame('login', $query['prompt'] ?? null);
        self::assertArrayNotHasKey('empty', $query);
        self::assertArrayNotHasKey('missing', $query);
    }

    public function testDisabledClientThrowsWhenBuildingAuthorizationUrl(): void
    {
        $client = new TestAuthClient([
            'enabled' => false,
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The 'stub' social provider is disabled.");

        $client->getAuthorizationUrl('https://app.example.test/callback', 'state-123');
    }

    public function testFetchUserAttributesNormalizesEmailUsernameAndName(): void
    {
        $client = new TestAuthClient([
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
            'scope' => 'openid profile email',
            'userInfoQuery' => ['lang' => 'en'],
            'tokenParams' => ['resource' => 'api'],
        ]);
        $captured = [];
        $httpClient = new class($captured) implements ClientInterface {
            /** @param array<int, array<string, mixed>> $calls */
            public function __construct(private array &$calls)
            {
            }

            #[\Override]
            public function send(string $method, string $url, array $headers = [], array $query = [], array $body = []): array
            {
                $this->calls[] = [
                    'method' => $method,
                    'url' => $url,
                    'headers' => $headers,
                    'query' => $query,
                    'body' => $body,
                ];

                if ($method === 'POST') {
                    return ['access_token' => 'token', 'user_id' => 'fallback-id'];
                }

                return [
                    'email' => 'person@example.test',
                    'first_name' => 'Person',
                    'last_name' => 'Example',
                ];
            }
        };

        $attributes = $client->fetchUserAttributes('auth-code', 'https://app.example.test/callback', $httpClient);

        self::assertSame(
            [
                'id' => 'fallback-id',
                'email' => 'person@example.test',
                'username' => 'person',
                'name' => 'Person Example',
            ],
            $attributes,
        );
        self::assertCount(2, $captured);
        self::assertSame('POST', $captured[0]['method']);
        self::assertSame('GET', $captured[1]['method']);
        self::assertSame('application/json', $captured[0]['headers']['Accept'] ?? null);
        self::assertSame('api', $captured[0]['body']['resource'] ?? null);
        self::assertSame('application/json', $captured[1]['headers']['Accept'] ?? null);
        self::assertSame('en', $captured[1]['query']['lang'] ?? null);
        self::assertSame('Bearer token', $captured[1]['headers']['Authorization'] ?? null);
    }

    public function testFetchUserAttributesThrowsWhenAccessTokenIsNotAString(): void
    {
        $client = new TestAuthClient([
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
        ]);
        $httpClient = new class implements ClientInterface {
            #[\Override]
            public function send(string $method, string $url, array $headers = [], array $query = [], array $body = []): array
            {
                return $method === 'POST' ? ['access_token' => false] : [];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The 'stub' access token is missing.");

        $client->fetchUserAttributes('auth-code', 'https://app.example.test/callback', $httpClient);
    }

    public function testFetchUserAttributesThrowsWhenIdCannotBeDetermined(): void
    {
        $client = new TestAuthClient([
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
        ]);
        $httpClient = new class implements ClientInterface {
            #[\Override]
            public function send(string $method, string $url, array $headers = [], array $query = [], array $body = []): array
            {
                if ($method === 'POST') {
                    return ['access_token' => 'token'];
                }

                return ['email' => 'person@example.test'];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unable to determine the 'stub' account identifier.");

        $client->fetchUserAttributes('auth-code', 'https://app.example.test/callback', $httpClient);
    }
    public function testGetAuthorizationUrlMergesAuthorizationParameters(): void
    {
        $client = new TestAuthClient([
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
            'authorizationParams' => ['audience' => 'api', 'prompt' => 'login'],
        ]);

        $url = $client->getAuthorizationUrl('https://app.example.test/callback', 'state-123');
        $parts = parse_url($url);

        self::assertIsArray($parts);
        parse_str($parts['query'] ?? '', $query);
        self::assertSame('client-id', $query['client_id'] ?? null);
        self::assertSame('https://app.example.test/callback', $query['redirect_uri'] ?? null);
        self::assertSame('code', $query['response_type'] ?? null);
        self::assertSame('default-scope', $query['scope'] ?? null);
        self::assertSame('state-123', $query['state'] ?? null);
        self::assertSame('api', $query['audience'] ?? null);
        self::assertSame('login', $query['prompt'] ?? null);
    }

    public function testIdentifierPrefersAttributeIdOverTokenUserId(): void
    {
        $client = new TestAuthClient();

        self::assertSame('attribute-id', $client->exposedIdentifier(
            ['id' => 'attribute-id'],
            ['user_id' => 'token-id'],
        ));
    }

    public function testMissingClientCredentialsThrowHelpfulErrors(): void
    {
        $client = new TestAuthClient();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The 'stub' clientId is not configured.");
        $client->exposedClientId();
    }

    public function testMissingClientSecretThrowsHelpfulError(): void
    {
        $client = new TestAuthClient(['clientId' => 'client-id']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The 'stub' clientSecret is not configured.");
        $client->exposedClientSecret();
    }

    public function testNormalizeUserAttributesKeepsZeroLikeNameParts(): void
    {
        $client = new TestAuthClient();

        self::assertSame(
            [
                'id' => 'abc',
                'email' => null,
                'username' => null,
                'name' => '0 Zero',
            ],
            $client->exposedNormalizeUserAttributes(
                ['id' => 'abc', 'first_name' => '0', 'last_name' => 'Zero'],
                [],
            ),
        );
    }

    public function testNormalizeUserAttributesTrimsAndFiltersPartialNames(): void
    {
        $client = new TestAuthClient();

        self::assertSame(
            [
                'id' => 'abc',
                'email' => null,
                'username' => null,
                'name' => 'Solo',
            ],
            $client->exposedNormalizeUserAttributes(
                ['id' => 'abc', 'first_name' => '', 'last_name' => ' Solo '],
                [],
            ),
        );
    }

    public function testProtectedHelpersRemainReachableThroughSubclass(): void
    {
        $client = new TestAuthClient([
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
            'authorizationParams' => ['prompt' => 'login'],
            'userInfoQuery' => ['lang' => 'en'],
        ]);

        self::assertSame(['prompt' => 'login'], $client->exposedAuthorizationParameters());
        self::assertSame('client-id', $client->exposedClientId());
        self::assertSame('secret', $client->exposedClientSecret());
        self::assertSame('42', $client->exposedIdentifier(['id' => 42], []));
        self::assertSame(['lang' => 'en'], $client->exposedUserInfoQuery([]));
        self::assertSame(['Accept' => 'application/json', 'Authorization' => 'Bearer token', 'User-Agent' => 'YiiRocks Voyti'], $client->exposedUserInfoHeaders(['access_token' => 'token']));
        self::assertSame('42', $client->exposedFirstString(['id' => 42], ['id']));
    }

    public function testResolveRedirectUriRejectsNonStringOverride(): void
    {
        $client = new TestAuthClient([
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
            'redirectUri' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The 'stub' redirect URI is not configured.");

        $client->exposedResolveRedirectUri('https://fallback.example.test/callback');
    }

    public function testResolveRedirectUriUsesConfiguredOverride(): void
    {
        $client = new TestAuthClient([
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
            'redirectUri' => 'https://configured.example.test/callback',
        ]);

        self::assertSame(
            'https://configured.example.test/callback',
            $client->exposedResolveRedirectUri('https://fallback.example.test/callback'),
        );
    }

    public function testScopeFallsBackToDefaultWhenConfiguredValueIsInvalid(): void
    {
        $client = new TestAuthClient([
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
            'scope' => ['invalid'],
        ]);

        self::assertSame('default-scope', $client->exposedScope());
    }

    public function testScopeUsesConfiguredStringValue(): void
    {
        $client = new TestAuthClient([
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
            'scope' => 'custom-scope',
        ]);

        self::assertSame('custom-scope', $client->exposedScope());
    }
}

final class TestAuthClient extends AbstractAuthClient
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct(
            'stub',
            'Stub',
            'https://provider.example.test/auth',
            'https://provider.example.test/token',
            'https://provider.example.test/userinfo',
            'default-scope',
            $config,
        );
    }

    /** @return array<string, string> */
    public function exposedAuthorizationParameters(): array
    {
        return $this->authorizationParameters();
    }

    public function exposedClientId(): string
    {
        return $this->clientId();
    }

    public function exposedClientSecret(): string
    {
        return $this->clientSecret();
    }

    /** @param array<string, mixed> $data @param list<string> $keys */
    public function exposedFirstString(array $data, array $keys): ?string
    {
        return $this->firstString($data, $keys);
    }

    /** @param array<string, mixed> $attributes @param array<string, mixed> $tokenData */
    public function exposedIdentifier(array $attributes, array $tokenData): string
    {
        return $this->identifier($attributes, $tokenData);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $tokenData
     *
     * @return array{id: string, email: null|string, username: null|string, name: null|string}
     */
    public function exposedNormalizeUserAttributes(array $attributes, array $tokenData): array
    {
        return $this->normalizeUserAttributes($attributes, $tokenData);
    }

    public function exposedResolveRedirectUri(string $fallback): string
    {
        return $this->resolveRedirectUri($fallback);
    }

    public function exposedScope(): string
    {
        return $this->scope();
    }

    /** @param array<string, mixed> $tokenData @return array<string, string> */
    public function exposedUserInfoHeaders(array $tokenData): array
    {
        return $this->userInfoHeaders($tokenData);
    }

    /** @param array<string, mixed> $tokenData @return array<string, string> */
    public function exposedUserInfoQuery(array $tokenData): array
    {
        return $this->userInfoQuery($tokenData);
    }
}
