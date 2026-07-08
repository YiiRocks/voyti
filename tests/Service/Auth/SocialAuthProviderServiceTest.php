<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Auth;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use YiiRocks\Voyti\AuthClient\AuthClientInterface;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Http\ClientInterface;
use YiiRocks\Voyti\Service\Auth\SocialAuthProviderService;
use YiiRocks\Voyti\tests\Support\FakeSession;
use Yiisoft\Router\UrlGeneratorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class SocialAuthProviderServiceTest extends TestCase
{
    private FakeSession $session;

    protected function setUp(): void
    {
        $this->session = new FakeSession();
        $this->session->open();
    }

    public function testBeginPassesProviderToRedirectUri(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');
        $client->method('getAuthorizationUrl')->willReturn('https://github.com/oauth/authorize?state=xyz');

        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->method('generateAbsolute')->willReturn('https://example.com/callback');
        $url
            ->expects(self::once())
            ->method('generateAbsolute')
            ->with('callback_route', self::callback(fn (array $params): bool => ($params['provider'] ?? null) === 'github'));

        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);
        $service->begin('github', 'callback_route');
    }

    public function testBeginReturnsAuthorizationUrl(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');
        $client->method('getAuthorizationUrl')->willReturn('https://github.com/oauth/authorize?state=xyz');

        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->method('generateAbsolute')->willReturn('https://example.com/callback');

        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);
        $authUrl = $service->begin('github', 'callback_route');

        self::assertSame('https://github.com/oauth/authorize?state=xyz', $authUrl);

        $stateKey = null;
        foreach ($this->session->all() as $key => $value) {
            if (str_starts_with($key, 'voyti.social_auth.state.')) {
                $stateKey = $key;
                break;
            }
        }
        self::assertNotNull($stateKey);
        self::assertIsString($this->session->get($stateKey));
    }

    public function testBeginStoresStateOfLength32(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');
        $client->method('getAuthorizationUrl')->willReturn('https://github.com/oauth/authorize?state=xyz');

        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->method('generateAbsolute')->willReturn('https://example.com/callback');

        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);
        $service->begin('github', 'callback_route');

        $stateKey = 'voyti.social_auth.state.' . md5('callback_route:github');
        self::assertSame(32, strlen((string) $this->session->get($stateKey)));
    }

    public function testBeginWithUnknownProviderThrowsException(): void
    {
        $registry = new AuthClientRegistry();
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);

        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The 'unknown' social provider is not configured.");
        $service->begin('unknown', 'route');
    }

    public function testCompleteReturnsUserAttributesOnSuccess(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');
        $client->method('fetchUserAttributes')->willReturn(['id' => '123', 'email' => 'user@example.com']);

        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->method('generateAbsolute')->willReturn('https://example.com/callback');

        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        $stateKey = 'voyti.social_auth.state.' . md5('callback_route:github');
        $this->session->set($stateKey, 'valid_state');

        $attributes = $service->complete('github', 'callback_route', ['code' => 'auth_code', 'state' => 'valid_state']);

        self::assertSame(['id' => '123', 'email' => 'user@example.com'], $attributes);
        self::assertFalse($this->session->has($stateKey));
    }

    public function testCompleteThrowsOnEmptyCode(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');
        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);

        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        $this->session->set('voyti.social_auth.state.' . md5('route:github'), 'valid_state');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The social authentication code is missing.');
        $service->complete('github', 'route', ['code' => '', 'state' => 'valid_state']);
    }

    public function testCompleteThrowsOnError(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');
        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);

        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('access_denied');
        $service->complete('github', 'route', ['error' => 'access_denied']);
    }

    public function testCompleteThrowsOnErrorDescription(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');
        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);

        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User cancelled');
        $service->complete('github', 'route', ['error_description' => 'User cancelled']);
    }

    public function testCompleteThrowsOnErrorDescriptionEvenWhenErrorPresent(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');
        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);

        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User cancelled');
        $service->complete('github', 'route', ['error' => 'access_denied', 'error_description' => 'User cancelled']);
    }

    public function testCompleteThrowsOnMismatchedState(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');
        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);

        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        $this->session->set('voyti.social_auth.state.' . md5('route:github'), 'expected_state');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The social authentication state is invalid or expired.');
        $service->complete('github', 'route', ['code' => 'some_code', 'state' => 'wrong_state']);
    }

    public function testCompleteThrowsOnMissingCode(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');
        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);

        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        $this->session->set('voyti.social_auth.state.' . md5('route:github'), 'valid_state');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The social authentication code is missing.');
        $service->complete('github', 'route', ['state' => 'valid_state']);
    }

    public function testCompleteThrowsOnNullOrNonStringStoredState(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');
        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);

        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The social authentication state is invalid or expired.');
        $service->complete('github', 'route', ['code' => 'c', 'state' => 'valid_state']);
    }

    public function testCompleteWithNullStateInParamsThrows(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn('github');
        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);

        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        $stateKey = 'voyti.social_auth.state.' . md5('route:github');
        $this->session->set($stateKey, 'valid_state');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The social authentication state is invalid or expired.');
        $service->complete('github', 'route', ['code' => 'c', 'state' => null]);
    }

    public function testHasCallbackParametersReturnsFalseForEmptyParams(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);
        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        self::assertFalse($service->hasCallbackParameters([]));
    }

    public function testHasCallbackParametersReturnsFalseForUnrelatedParams(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);
        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        self::assertFalse($service->hasCallbackParameters(['foo' => 'bar']));
    }

    public function testHasCallbackParametersReturnsTrueForCode(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);
        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        self::assertTrue($service->hasCallbackParameters(['code' => 'abc']));
    }

    public function testHasCallbackParametersReturnsTrueForError(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);
        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        self::assertTrue($service->hasCallbackParameters(['error' => 'access_denied']));
    }

    public function testHasCallbackParametersReturnsTrueForErrorDescription(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);
        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        self::assertTrue($service->hasCallbackParameters(['error_description' => 'desc']));
    }

    public function testHasCallbackParametersReturnsTrueForState(): void
    {
        $client = $this->createMock(AuthClientInterface::class);
        $registry = new AuthClientRegistry($client);
        $httpClient = $this->createMock(ClientInterface::class);
        $url = $this->createMock(UrlGeneratorInterface::class);
        $service = new SocialAuthProviderService($registry, $httpClient, $this->session, $url);

        self::assertTrue($service->hasCallbackParameters(['state' => 'xyz']));
    }
}
