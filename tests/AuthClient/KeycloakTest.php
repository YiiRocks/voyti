<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\Keycloak;
use YiiRocks\Voyti\Http\ClientInterface;

final class KeycloakTest extends TestCase
{
    public function testAuthorizationUrlUsesNormalizedBaseUrlAndRealm(): void
    {
        $client = new Keycloak([
            'baseUrl' => 'https://identity.example.test/',
            'realm' => ' demo ',
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
        ]);

        $url = $client->getAuthorizationUrl('https://app.example.test/callback', 'test-state');
        $parts = parse_url($url);

        self::assertIsArray($parts);
        self::assertSame('/realms/demo/protocol/openid-connect/auth', $parts['path'] ?? null);

        parse_str($parts['query'] ?? '', $query);
        self::assertSame('client-id', $query['client_id'] ?? null);
        self::assertSame('https://app.example.test/callback', $query['redirect_uri'] ?? null);
        self::assertSame('code', $query['response_type'] ?? null);
        self::assertSame('openid email profile', $query['scope'] ?? null);
        self::assertSame('test-state', $query['state'] ?? null);
    }

    public function testFetchUserAttributesUsesNormalizedTokenAndUserInfoUrls(): void
    {
        $captured = new \stdClass();
        $captured->calls = [];

        $httpClient = new class($captured) implements ClientInterface {
            public function __construct(private readonly \stdClass $captured)
            {
            }

            #[\Override]
            public function send(
                string $method,
                string $url,
                array $headers = [],
                array $query = [],
                array $body = [],
            ): array {
                $this->captured->calls[] = [
                    'method' => $method,
                    'url' => $url,
                    'headers' => $headers,
                    'query' => $query,
                    'body' => $body,
                ];

                if (count($this->captured->calls) === 1) {
                    return ['access_token' => 'access-token'];
                }

                return [
                    'sub' => 'kc-user-1',
                    'email' => 'person@example.test',
                    'preferred_username' => 'person',
                    'name' => 'Person Example',
                ];
            }
        };

        $client = new Keycloak([
            'baseUrl' => 'https://identity.example.test/',
            'realm' => ' demo ',
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
        ]);

        $attributes = $client->fetchUserAttributes('auth-code', 'https://app.example.test/callback', $httpClient);

        self::assertSame(
            [
                'id' => 'kc-user-1',
                'email' => 'person@example.test',
                'username' => 'person',
                'name' => 'Person Example',
            ],
            $attributes,
        );
        self::assertCount(2, $captured->calls);
        self::assertSame(
            'https://identity.example.test/realms/demo/protocol/openid-connect/token',
            $captured->calls[0]['url'],
        );
        self::assertSame(
            [
                'client_id' => 'client-id',
                'client_secret' => 'secret',
                'code' => 'auth-code',
                'grant_type' => 'authorization_code',
                'redirect_uri' => 'https://app.example.test/callback',
            ],
            $captured->calls[0]['body'],
        );
        self::assertSame(
            'https://identity.example.test/realms/demo/protocol/openid-connect/userinfo',
            $captured->calls[1]['url'],
        );
    }

    public function testInvalidBaseUrlAndRealmValuesAreIgnored(): void
    {
        $client = new Keycloak([
            'baseUrl' => 123,
            'realm' => ['demo'],
            'clientId' => 'client-id',
            'clientSecret' => 'secret',
        ]);

        $url = $client->getAuthorizationUrl('https://app.example.test/callback', 'test-state');

        self::assertStringStartsWith('/realms//protocol/openid-connect/auth?', $url);
    }
}
