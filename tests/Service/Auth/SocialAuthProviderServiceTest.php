<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Auth;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stringable;
use YiiRocks\Voyti\AuthClient\AuthClientInterface;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Http\ClientInterface;
use YiiRocks\Voyti\Service\Auth\SocialAuthProviderService;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;

final class SocialAuthProviderServiceTest extends TestCase
{
    public function testBeginStoresStateAndReturnsAuthorizationUrl(): void
    {
        $session = $this->session();
        $client = new StubAuthClient();
        $service = new SocialAuthProviderService(
            new AuthClientRegistry($client),
            $this->createStub(ClientInterface::class),
            $session,
            $this->urlGenerator(),
        );

        $url = $service->begin('stub', 'voyti/auth');
        $storedState = $session->get($this->stateKey('stub', 'voyti/auth'));

        self::assertSame(32, strlen($storedState));
        self::assertStringContainsString('redirect=https://example.test/voyti/auth/stub', $url);
        self::assertStringContainsString('state=' . $storedState, $url);
        self::assertSame('https://example.test/voyti/auth/stub', $client->lastRedirectUri);
        self::assertSame($storedState, $client->lastState);
    }

    public function testBeginThrowsWhenProviderIsUnknown(): void
    {
        $service = new SocialAuthProviderService(
            new AuthClientRegistry(),
            $this->createStub(ClientInterface::class),
            $this->session(),
            $this->urlGenerator(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The 'missing' social provider is not configured.");

        $service->begin('missing', 'voyti/auth');
    }

    public function testCompleteReturnsFetchedUserAttributesAndRemovesState(): void
    {
        $session = $this->session();
        $client = new StubAuthClient([
            'id' => '42',
            'email' => 'person@example.test',
        ]);
        $service = new SocialAuthProviderService(
            new AuthClientRegistry($client),
            $this->createStub(ClientInterface::class),
            $session,
            $this->urlGenerator(),
        );
        $session->set($this->stateKey('stub', 'voyti/auth'), 'known-state');

        $attributes = $service->complete('stub', 'voyti/auth', [
            'state' => 'known-state',
            'code' => 'auth-code',
        ]);

        self::assertSame(['id' => '42', 'email' => 'person@example.test'], $attributes);
        self::assertSame('auth-code', $client->lastCode);
        self::assertSame('https://example.test/voyti/auth/stub', $client->lastRedirectUri);
        self::assertNull($session->get($this->stateKey('stub', 'voyti/auth')));
    }

    public function testCompleteThrowsForInvalidState(): void
    {
        $session = $this->session();
        $session->set($this->stateKey('stub', 'voyti/auth'), 'expected-state');
        $service = new SocialAuthProviderService(
            new AuthClientRegistry(new StubAuthClient()),
            $this->createStub(ClientInterface::class),
            $session,
            $this->urlGenerator(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The social authentication state is invalid or expired.');

        $service->complete('stub', 'voyti/auth', [
            'state' => 'wrong-state',
            'code' => 'auth-code',
        ]);
    }

    public function testCompleteThrowsForReturnedProviderError(): void
    {
        $service = new SocialAuthProviderService(
            new AuthClientRegistry(new StubAuthClient()),
            $this->createStub(ClientInterface::class),
            $this->session(),
            $this->urlGenerator(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        $service->complete('stub', 'voyti/auth', ['error_description' => 'Access denied']);
    }

    public function testCompleteThrowsForReturnedProviderErrorPrefersErrorDescriptionOverError(): void
    {
        $service = new SocialAuthProviderService(
            new AuthClientRegistry(new StubAuthClient()),
            $this->createStub(ClientInterface::class),
            $this->session(),
            $this->urlGenerator(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        $service->complete('stub', 'voyti/auth', [
            'error' => 'access_denied',
            'error_description' => 'Access denied',
        ]);
    }

    public function testCompleteThrowsWhenCodeIsMissing(): void
    {
        $session = $this->session();
        $session->set($this->stateKey('stub', 'voyti/auth'), 'known-state');
        $service = new SocialAuthProviderService(
            new AuthClientRegistry(new StubAuthClient()),
            $this->createStub(ClientInterface::class),
            $session,
            $this->urlGenerator(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The social authentication code is missing.');

        $service->complete('stub', 'voyti/auth', ['state' => 'known-state']);
    }

    public function testHasCallbackParametersDetectsOauthCallbackFields(): void
    {
        $service = new SocialAuthProviderService(
            new AuthClientRegistry(new StubAuthClient()),
            $this->createStub(ClientInterface::class),
            $this->session(),
            $this->urlGenerator(),
        );

        self::assertTrue($service->hasCallbackParameters(['code' => 'abc']));
        self::assertTrue($service->hasCallbackParameters(['state' => 'abc']));
        self::assertTrue($service->hasCallbackParameters(['error' => 'denied']));
        self::assertTrue($service->hasCallbackParameters(['error_description' => 'denied']));
        self::assertFalse($service->hasCallbackParameters(['foo' => 'bar']));
    }

    private function session(): SessionInterface
    {
        return new class implements SessionInterface {
            /** @var array<string, mixed> */
            private array $values = [];

            #[\Override]
            public function all(): array
            {
                return $this->values;
            }
            #[\Override]
            public function clear(): void
            {
                $this->values = [];
            }
            #[\Override]
            public function close(): void
            {
            }
            #[\Override]
            public function destroy(): void
            {
                $this->values = [];
            }
            #[\Override]
            public function discard(): void
            {
                $this->values = [];
            }
            #[\Override]
            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }
            #[\Override]
            public function getCookieParameters(): array
            {
                return [];
            }
            #[\Override]
            public function getId(): ?string
            {
                return 'test-session';
            }
            #[\Override]
            public function getName(): string
            {
                return 'TESTSESSID';
            }
            #[\Override]
            public function has(string $key): bool
            {
                return array_key_exists($key, $this->values);
            }
            #[\Override]
            public function isActive(): bool
            {
                return true;
            }
            #[\Override]
            public function open(): void
            {
            }
            #[\Override]
            public function pull(string $key, mixed $default = null): mixed
            {
                $value = $this->get($key, $default);
                $this->remove($key);
                return $value;
            }
            #[\Override]
            public function regenerateId(): void
            {
            }
            #[\Override]
            public function remove(string $key): void
            {
                unset($this->values[$key]);
            }
            #[\Override]
            public function set(string $key, mixed $value): void
            {
                $this->values[$key] = $value;
            }
            #[\Override]
            public function setId(string $sessionId): void
            {
            }
        };
    }

    private function stateKey(string $provider, string $routeName): string
    {
        return 'voyti.social_auth.state.' . md5($routeName . ':' . $provider);
    }

    private function urlGenerator(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            #[\Override]
            public function generate(string $name, array $arguments = [], array $queryParameters = [], ?string $hash = null): string
            {
                return '/' . trim($name, '/');
            }

            #[\Override]
            public function generateAbsolute(string $name, array $arguments = [], array $queryParameters = [], ?string $hash = null, ?string $scheme = null, ?string $host = null): string
            {
                $provider = $arguments['provider'] ?? '';
                return 'https://example.test/' . trim($name, '/') . '/' . $provider;
            }

            #[\Override]
            public function generateFromCurrent(array $replacedArguments = [], array $queryParameters = [], ?string $hash = null, ?string $fallbackRouteName = null): string
            {
                return '/' . trim($fallbackRouteName ?? 'current', '/');
            }

            #[\Override]
            public function getUriPrefix(): string
            {
                return '';
            }

            #[\Override]
            public function setDefaultArgument(string $name, Stringable|string|int|float|bool|null $value): void
            {
            }

            #[\Override]
            public function setUriPrefix(string $name): void
            {
            }
        };
    }
}

final class StubAuthClient implements AuthClientInterface
{
    public ?string $lastCode = null;
    public ?string $lastRedirectUri = null;
    public ?string $lastState = null;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(private array $attributes = [])
    {
    }

    #[\Override]
    public function fetchUserAttributes(string $code, string $redirectUri, ClientInterface $httpClient): array
    {
        $this->lastCode = $code;
        $this->lastRedirectUri = $redirectUri;
        return $this->attributes;
    }

    #[\Override]
    public function getAuthorizationUrl(string $redirectUri, string $state): string
    {
        $this->lastRedirectUri = $redirectUri;
        $this->lastState = $state;
        return 'redirect=' . $redirectUri . '&state=' . $state;
    }

    #[\Override]
    public function getName(): string
    {
        return 'stub';
    }

    #[\Override]
    public function getTitle(): string
    {
        return 'Stub';
    }

    #[\Override]
    public function isEnabled(): bool
    {
        return true;
    }
}
