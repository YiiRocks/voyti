<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\AbstractAuthClient;
use YiiRocks\Voyti\AuthClient\GenericAuthClient;
use YiiRocks\Voyti\Http\ClientInterface;

#[AllowMockObjectsWithoutExpectations]
final class AbstractAuthClientTest extends TestCase
{
    /**
     * @return iterable<string, array{bool|float|int, null|string}>
     */
    public static function firstStringCoercionProvider(): iterable
    {
        yield 'bool value is discarded' => [true, null];
        yield 'float value is stringified' => [12.5, '12.5'];
        yield 'integer value is stringified' => [12345, '12345'];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function isEnabledProvider(): iterable
    {
        yield 'by default' => [['clientId' => 'id', 'clientSecret' => 'secret'], true];
        yield 'casts to bool' => [['enabled' => 1, 'clientId' => 'id', 'clientSecret' => 'secret'], true];
        yield 'returns false when disabled' => [['enabled' => false, 'clientId' => 'id', 'clientSecret' => 'secret'], false];
    }

    public function testAppendQueryWithEmptyQueryString(): void
    {
        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);
        $method = new \ReflectionMethod(AbstractAuthClient::class, 'appendQuery');

        $result = $method->invoke($client, 'https://example.com/auth', []);
        self::assertSame('https://example.com/auth', $result);

        $result = $method->invoke($client, 'https://example.com/auth?existing=true', []);
        self::assertSame('https://example.com/auth?existing=true', $result);
    }

    public function testAuthorizationHeaders(): void
    {
        $capturedHeaders = null;
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (
                string $method,
                string $url,
                array $headers = [],
                array $query = [],
                array $body = [],
            ) use (&$capturedHeaders): array {
                if ($method === 'GET') {
                    $capturedHeaders = $headers;
                    return ['id' => 'uid', 'email' => 'user@test.com'];
                }
                return ['access_token' => 'my_token'];
            });

        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);
        $client->fetchUserAttributes('code', 'https://cb.com', $httpClient);

        self::assertNotNull($capturedHeaders);
        self::assertSame('application/json', $capturedHeaders['Accept']);
        self::assertSame('Bearer my_token', $capturedHeaders['Authorization']);
        self::assertSame('YiiRocks Voyti', $capturedHeaders['User-Agent']);
    }

    public function testAuthorizationUrlWithExistingQuery(): void
    {
        $client = new GenericAuthClient(
            'test',
            'Test',
            'https://example.com/auth?existing=true',
            'https://example.com/token',
            'https://example.com/userinfo',
            'email',
            ['clientId' => 'id', 'clientSecret' => 'secret'],
        );

        $url = $client->getAuthorizationUrl('https://cb.com', 'state');

        self::assertStringContainsString('existing=true', $url);
        self::assertStringContainsString('&client_id=id', $url);
    }

    public function testClientIdThrowsWhenNotConfigured(): void
    {
        $client = $this->createClient([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("The 'test_provider' clientId is not configured.");

        $client->getAuthorizationUrl('https://cb.com', 'state');
    }

    public function testClientSecretThrowsWhenNotConfigured(): void
    {
        $client = $this->createClient(['clientId' => 'id']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("The 'test_provider' clientSecret is not configured.");

        $client->fetchUserAttributes('code', 'https://cb.com', $this->createMock(ClientInterface::class));
    }

    public function testExchangeHeaders(): void
    {
        $capturedHeaders = null;
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (
                string $method,
                string $url,
                array $headers = [],
                array $query = [],
                array $body = [],
            ) use (&$capturedHeaders): array {
                if ($method === 'POST') {
                    $capturedHeaders = $headers;
                    return ['access_token' => 'token'];
                }
                return ['id' => 'uid'];
            });

        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);
        $client->fetchUserAttributes('code', 'https://cb.com', $httpClient);

        self::assertNotNull($capturedHeaders);
        self::assertSame('application/json', $capturedHeaders['Accept']);
        self::assertSame('YiiRocks Voyti', $capturedHeaders['User-Agent']);
    }

    public function testFetchUserAttributesReturnsNormalizedAttributes(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($url === 'https://example.com/token') {
                    return ['access_token' => 'access123', 'user_id' => 'provider_uid'];
                }
                if ($url === 'https://example.com/userinfo') {
                    return ['id' => 'provider_uid', 'email' => 'user@example.com', 'login' => 'testuser', 'name' => 'Test User'];
                }
                return [];
            });

        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);

        $result = $client->fetchUserAttributes('auth_code', 'https://example.com/callback', $httpClient);

        self::assertSame('provider_uid', $result['id']);
        self::assertSame('user@example.com', $result['email']);
        self::assertSame('testuser', $result['username']);
        self::assertSame('Test User', $result['name']);
    }

    public function testFetchUserAttributesThrowsWhenDisabled(): void
    {
        $client = $this->createClient(['enabled' => false]);
        $httpClient = $this->createMock(ClientInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("The 'test_provider' social provider is disabled.");

        $client->fetchUserAttributes('code', 'https://example.com/callback', $httpClient);
    }

    public function testFetchUserAttributesThrowsWhenNoIdReturned(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnOnConsecutiveCalls(
                ['access_token' => 'token123'],
                ['email' => 'user@example.com'],
            );

        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Unable to determine the 'test_provider' account identifier.");

        $client->fetchUserAttributes('auth_code', 'https://example.com/callback', $httpClient);
    }

    public function testFetchUserAttributesWithSubIdentifier(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($method === 'POST') {
                    return ['access_token' => 'token'];
                }
                return ['sub' => 'sub_id', 'email' => 'user@example.com'];
            });

        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);
        $result = $client->fetchUserAttributes('code', 'https://example.com/cb', $httpClient);

        self::assertSame('sub_id', $result['id']);
    }

    public function testFetchWithNameFromDisplayName(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($method === 'POST') {
                    return ['access_token' => 'token'];
                }
                return ['id' => 'uid', 'email' => 'u@test.com', 'display_name' => 'Display Name'];
            });

        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);
        $result = $client->fetchUserAttributes('code', 'https://cb.com', $httpClient);

        self::assertSame('Display Name', $result['name']);
        self::assertSame('Display Name', $result['username']);
    }

    public function testFetchWithUserInfoQueryConfig(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (
                string $method,
                string $url,
                array $headers = [],
                array $query = [],
                array $body = [],
            ) use (&$capturedQuery): array {
                if ($method === 'POST') {
                    return ['access_token' => 'token'];
                }
                return ['id' => 'uid', 'email' => 'user@test.com'];
            });

        $client = $this->createClient([
            'clientId' => 'id',
            'clientSecret' => 'secret',
            'userInfoQuery' => ['v' => '1.0'],
        ]);
        $result = $client->fetchUserAttributes('code', 'https://cb.com', $httpClient);

        self::assertSame('user@test.com', $result['email']);
    }

    #[DataProvider('firstStringCoercionProvider')]
    public function testFirstStringCoercion(bool|float|int $emailValue, ?string $expectedEmail): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url) use ($emailValue): array {
                if ($method === 'POST') {
                    return ['access_token' => 'token'];
                }
                return ['id' => 'uid', 'email' => $emailValue];
            });

        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);
        $result = $client->fetchUserAttributes('code', 'https://cb.com', $httpClient);

        self::assertSame($expectedEmail, $result['email']);
    }

    public function testGetAuthorizationUrlReturnsUrl(): void
    {
        $client = $this->createClient([
            'clientId' => 'my_client_id',
            'clientSecret' => 'secret',
        ]);

        $url = $client->getAuthorizationUrl('https://example.com/callback', 'state123');

        self::assertStringContainsString('client_id=my_client_id', $url);
        self::assertStringContainsString('redirect_uri=https%3A%2F%2Fexample.com%2Fcallback', $url);
        self::assertStringContainsString('response_type=code', $url);
        self::assertStringContainsString('scope=email+profile', $url);
        self::assertStringContainsString('state=state123', $url);
        self::assertStringContainsString('?', $url);
        self::assertStringStartsWith('https://example.com/auth?', $url);
    }

    public function testGetAuthorizationUrlThrowsWhenDisabled(): void
    {
        $client = $this->createClient(['enabled' => false]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("The 'test_provider' social provider is disabled.");

        $client->getAuthorizationUrl('https://example.com/callback', 'state');
    }

    public function testGetAuthorizationUrlWithCustomParams(): void
    {
        $client = $this->createClient([
            'clientId' => 'id',
            'clientSecret' => 'secret',
            'authorizationParams' => ['access_type' => 'offline', 'prompt' => 'consent'],
        ]);

        $url = $client->getAuthorizationUrl('https://example.com/callback', 'state');

        self::assertStringContainsString('access_type=offline', $url);
        self::assertStringContainsString('prompt=consent', $url);
    }

    public function testGetAuthorizationUrlWithCustomRedirectUri(): void
    {
        $client = $this->createClient([
            'clientId' => 'id',
            'clientSecret' => 'secret',
            'redirectUri' => 'https://custom-redirect.com/cb',
        ]);

        $url = $client->getAuthorizationUrl('https://fallback.com/cb', 'state');

        self::assertStringContainsString('redirect_uri=https%3A%2F%2Fcustom-redirect.com%2Fcb', $url);
    }

    public function testGetAuthorizationUrlWithCustomScope(): void
    {
        $client = $this->createClient([
            'clientId' => 'id',
            'clientSecret' => 'secret',
            'scope' => 'custom_scope',
        ]);

        $url = $client->getAuthorizationUrl('https://example.com/callback', 'state');

        self::assertStringContainsString('scope=custom_scope', $url);
    }

    public function testGetAuthorizationUrlWithEmptyRedirectUriThrows(): void
    {
        $client = $this->createClient([
            'clientId' => 'id',
            'clientSecret' => 'secret',
            'redirectUri' => '',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("The 'test_provider' redirect URI is not configured.");

        $client->getAuthorizationUrl('', 'state');
    }

    public function testGetName(): void
    {
        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertSame('test_provider', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertSame('Test Provider', $client->getTitle());
    }

    public function testIdentifierFromTokenData(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($method === 'POST') {
                    return ['access_token' => 'token', 'user_id' => 'user_from_token'];
                }
                return ['email' => 'user@test.com'];
            });

        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);
        $result = $client->fetchUserAttributes('code', 'https://cb.com', $httpClient);

        self::assertSame('user_from_token', $result['id']);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('isEnabledProvider')]
    public function testIsEnabled(array $config, bool $expected): void
    {
        $client = $this->createClient($config);
        self::assertSame($expected, $client->isEnabled());
    }

    public function testNormalizeNameWithSpaces(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($method === 'POST') {
                    return ['access_token' => 'token'];
                }
                return ['id' => 'uid', 'first_name' => '  John  ', 'last_name' => '  Doe  '];
            });

        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);
        $result = $client->fetchUserAttributes('code', 'https://cb.com', $httpClient);

        self::assertNotNull($result['name']);
        self::assertSame('John     Doe', $result['name']);
    }

    public function testNormalizeUserAttributesWithEmptyNameFromFirstLast(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($method === 'POST') {
                    return ['access_token' => 'token'];
                }
                return ['id' => 'uid', 'first_name' => null, 'last_name' => null];
            });

        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);
        $result = $client->fetchUserAttributes('code', 'https://cb.com', $httpClient);

        self::assertNull($result['name']);
    }

    public function testNormalizeUserAttributesWithFirstAndLastName(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url): array {
                if ($method === 'POST') {
                    return ['access_token' => 'token'];
                }
                return ['id' => 'uid', 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@test.com'];
            });

        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);
        $result = $client->fetchUserAttributes('code', 'https://cb.com', $httpClient);

        self::assertSame('John Doe', $result['name']);
    }

    public function testProtectedMethodsAccessibleFromSubclass(): void
    {
        $client = $this->createClient([
            'clientId' => 'my_id',
            'clientSecret' => 'my_secret',
            'redirectUri' => 'https://redirect.me',
            'scope' => 'custom',
            'authorizationParams' => ['prompt' => 'login'],
            'userInfoQuery' => ['format' => 'json'],
        ]);

        $authorizationParams = (\Closure::bind(
            fn(): array => $this->authorizationParameters(),
            $client,
            GenericAuthClient::class,
        ))();
        self::assertSame(['prompt' => 'login'], $authorizationParams);

        $clientId = (\Closure::bind(
            fn(): string => $this->clientId(),
            $client,
            GenericAuthClient::class,
        ))();
        self::assertSame('my_id', $clientId);

        $clientSecret = (\Closure::bind(
            fn(): string => $this->clientSecret(),
            $client,
            GenericAuthClient::class,
        ))();
        self::assertSame('my_secret', $clientSecret);

        $scope = (\Closure::bind(
            fn(): string => $this->scope(),
            $client,
            GenericAuthClient::class,
        ))();
        self::assertSame('custom', $scope);

        $redirectUri = (\Closure::bind(
            fn(string $fallback): string => $this->resolveRedirectUri($fallback),
            $client,
            GenericAuthClient::class,
        ))('https://fallback');
        self::assertSame('https://redirect.me', $redirectUri);

        $userInfoQuery = (\Closure::bind(
            fn(array $tokenData): array => $this->userInfoQuery($tokenData),
            $client,
            GenericAuthClient::class,
        ))(['access_token' => 't']);
        self::assertSame(['format' => 'json'], $userInfoQuery);

        $identifier = (\Closure::bind(
            fn(array $attributes, array $tokenData): string => $this->identifier($attributes, $tokenData),
            $client,
            GenericAuthClient::class,
        ))(['id' => 'attr_id'], ['user_id' => 'tok_id']);
        self::assertSame('attr_id', $identifier);

        $normalized = (\Closure::bind(
            fn(array $attributes, array $tokenData): array => $this->normalizeUserAttributes($attributes, $tokenData),
            $client,
            GenericAuthClient::class,
        ))(['id' => 'nid', 'email' => 'n@t.com'], []);
        self::assertSame('nid', $normalized['id']);
        self::assertSame('n@t.com', $normalized['email']);
    }

    public function testResolveRedirectUriWithFallback(): void
    {
        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);

        $redirectUri = (\Closure::bind(
            fn(string $fallback): string => $this->resolveRedirectUri($fallback),
            $client,
            GenericAuthClient::class,
        ))('https://fallback.me');
        self::assertSame('https://fallback.me', $redirectUri);
    }

    public function testResolveRedirectUriWithNonStringConfigThrows(): void
    {
        $client = $this->createClient([
            'clientId' => 'id',
            'clientSecret' => 'secret',
            'redirectUri' => 123,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("The 'test_provider' redirect URI is not configured.");

        (\Closure::bind(
            fn(string $fallback): string => $this->resolveRedirectUri($fallback),
            $client,
            GenericAuthClient::class,
        ))('');
    }

    public function testScopeWithNonStringConfigReturnsDefault(): void
    {
        $client = $this->createClient([
            'clientId' => 'id',
            'clientSecret' => 'secret',
            'scope' => 123,
        ]);

        $scope = (\Closure::bind(
            fn(): string => $this->scope(),
            $client,
            GenericAuthClient::class,
        ))();
        self::assertSame('email profile', $scope);
    }

    public function testStringMapWithEmptyKey(): void
    {
        $client = $this->createClient([
            'clientId' => 'id',
            'clientSecret' => 'secret',
            'authorizationParams' => [
                '' => 'should_not_appear',
                'valid' => 'should_appear',
            ],
        ]);

        $url = $client->getAuthorizationUrl('https://cb.com', 'state');

        self::assertStringNotContainsString('should_not_appear', $url);
        self::assertStringContainsString('should_appear', $url);
    }

    public function testStringMapWithNonConvertibleValue(): void
    {
        $client = $this->createClient([
            'clientId' => 'id',
            'clientSecret' => 'secret',
            'authorizationParams' => [
                'valid' => 'should_appear',
                'invalid' => ['not', 'string'],
            ],
        ]);

        $url = $client->getAuthorizationUrl('https://cb.com', 'state');

        self::assertStringContainsString('should_appear', $url);
        self::assertStringNotContainsString('invalid', $url);
    }

    public function testStringMapWithVariousTypes(): void
    {
        $client = $this->createClient([
            'clientId' => 'id',
            'clientSecret' => 'secret',
            'authorizationParams' => [
                'str' => 'hello',
                'int' => 42,
                'float' => 3.14,
                'bool_true' => true,
                'bool_false' => false,
            ],
        ]);

        $url = $client->getAuthorizationUrl('https://cb.com', 'state');

        self::assertStringContainsString('str=hello', $url);
        self::assertStringContainsString('int=42', $url);
        self::assertStringContainsString('float=3.14', $url);
        self::assertStringContainsString('bool_true=1', $url);
        self::assertStringNotContainsString('bool_false', $url);
    }

    public function testTokenBodyWithNonArrayTokenParams(): void
    {
        $client = $this->createClient([
            'clientId' => 'id',
            'clientSecret' => 'secret',
            'tokenParams' => 'not_an_array',
        ]);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url, array $headers = [], array $query = [], array $body = []) {
                if ($method === 'POST') {
                    self::assertArrayNotHasKey('audience', $body);
                    return ['access_token' => 'token'];
                }
                return ['id' => '123'];
            });

        $client->fetchUserAttributes('code', 'https://example.com/cb', $httpClient);
    }

    public function testTokenRequestBodyFields(): void
    {
        $capturedBody = null;
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (
                string $method,
                string $url,
                array $headers = [],
                array $query = [],
                array $body = [],
            ) use (&$capturedBody): array {
                if ($method === 'POST') {
                    $capturedBody = $body;
                    return ['access_token' => 'the_token'];
                }
                return ['id' => 'uid'];
            });

        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);
        $client->fetchUserAttributes('my_code', 'https://cb.com', $httpClient);

        self::assertNotNull($capturedBody);
        self::assertSame('id', $capturedBody['client_id']);
        self::assertSame('secret', $capturedBody['client_secret']);
        self::assertSame('my_code', $capturedBody['code']);
        self::assertSame('authorization_code', $capturedBody['grant_type']);
        self::assertSame('https://cb.com', $capturedBody['redirect_uri']);
    }

    public function testTokenRequestBodyMergesConfigParams(): void
    {
        $client = $this->createClient([
            'clientId' => 'id',
            'clientSecret' => 'secret',
            'tokenParams' => ['audience' => 'api'],
        ]);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url, array $headers = [], array $query = [], array $body = []) {
                if ($method === 'POST') {
                    self::assertArrayHasKey('audience', $body);
                    self::assertSame('api', $body['audience']);
                    return ['access_token' => 'token'];
                }
                return ['id' => '123'];
            });

        $client->fetchUserAttributes('code', 'https://example.com/cb', $httpClient);
    }

    public function testUserInfoHeadersNotUsedInFetchWhenTokenMissing(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('send')
            ->willReturn([]);

        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("The 'test_provider' access token is missing.");

        $client->fetchUserAttributes('code', 'https://cb.com', $httpClient);
    }

    public function testUserInfoQuerySent(): void
    {
        $capturedQuery = null;
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (
                string $method,
                string $url,
                array $headers = [],
                array $query = [],
                array $body = [],
            ) use (&$capturedQuery): array {
                if ($method === 'GET') {
                    $capturedQuery = $query;
                    return ['id' => 'uid', 'email' => 'user@test.com'];
                }
                return ['access_token' => 'token'];
            });

        $client = $this->createClient([
            'clientId' => 'id',
            'clientSecret' => 'secret',
            'userInfoQuery' => ['v' => '1.0', 'format' => 'json'],
        ]);
        $client->fetchUserAttributes('code', 'https://cb.com', $httpClient);

        self::assertNotNull($capturedQuery);
        self::assertSame('1.0', $capturedQuery['v']);
    }

    /**
     * @param array<string, mixed> $userInfoResponse
     */
    #[DataProvider('usernameDerivationProvider')]
    public function testUsernameDerivation(array $userInfoResponse, string $expectedUsername): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $url) use ($userInfoResponse): array {
                if ($method === 'POST') {
                    return ['access_token' => 'token'];
                }
                return $userInfoResponse;
            });

        $client = $this->createClient(['clientId' => 'id', 'clientSecret' => 'secret']);
        $result = $client->fetchUserAttributes('code', 'https://cb.com', $httpClient);

        self::assertSame($expectedUsername, $result['username']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function usernameDerivationProvider(): iterable
    {
        yield 'email with no username field' => [['id' => 'uid', 'email' => 'user@example.com'], 'user'];
        yield 'nickname field' => [['id' => 'uid', 'email' => 'user@test.com', 'nickname' => 'nickuser'], 'nickuser'];
        yield 'preferred_username field' => [
            ['id' => 'uid', 'email' => 'user@test.com', 'preferred_username' => 'pref_user'],
            'pref_user',
        ];
        yield 'screen_name field' => [
            ['id' => 'uid', 'email' => 'user@test.com', 'screen_name' => 'screenuser'],
            'screenuser',
        ];
        yield 'email missing at symbol' => [['id' => 'uid', 'email' => 'noatsign'], 'noatsign'];
        yield 'email with dot before at' => [['id' => 'uid', 'email' => 'user.name@example.com'], 'user.name'];
        yield 'email starting with at' => [['id' => 'uid', 'email' => '@domain.com'], '@domain.com'];
    }
    private function createClient(array $config = []): GenericAuthClient
    {
        return new GenericAuthClient(
            'test_provider',
            'Test Provider',
            'https://example.com/auth',
            'https://example.com/token',
            'https://example.com/userinfo',
            'email profile',
            $config,
        );
    }
}
