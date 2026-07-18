<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\AuthClientRegistryFactory;
use YiiRocks\Voyti\AuthClient\Facebook;
use YiiRocks\Voyti\AuthClient\GenericAuthClient;
use YiiRocks\Voyti\AuthClient\GitHub;
use YiiRocks\Voyti\AuthClient\Twitter;
use YiiRocks\Voyti\AuthClient\VKontakte;
use YiiRocks\Voyti\AuthClient\Yandex;
use YiiRocks\Voyti\ModuleConfig;

final class AuthClientRegistryFactoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, class-string, string}>
     */
    public static function dedicatedClientProvider(): iterable
    {
        yield 'facebook' => ['facebook', Facebook::class, 'facebook'];
        yield 'github' => ['github', GitHub::class, 'github'];
        yield 'twitter/x' => ['x', Twitter::class, 'x'];
        yield 'vkontakte' => ['vkontakte', VKontakte::class, 'vkontakte'];
        yield 'yandex' => ['yandex', Yandex::class, 'yandex'];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function genericClientProvider(): iterable
    {
        yield 'google' => ['google', 'google', 'Google'];
        yield 'linkedin' => ['linkedin', 'linkedin', 'LinkedIn'];
        yield 'microsoft365' => ['microsoft365', 'microsoft365', 'Microsoft 365'];
    }

    /**
     * @return iterable<string, array{null|string, null|string, string, string}>
     */
    public static function keycloakConfigVariantProvider(): iterable
    {
        yield 'empty base url' => ['', 'testrealm', 'starts', '/realms/testrealm'];
        yield 'missing base url' => [null, 'testrealm', 'starts', '/realms/testrealm/protocol/openid-connect/auth'];
        yield 'missing realm' => ['https://auth.example.com', null, 'starts', 'https://auth.example.com/realms/'];
        yield 'trailing slash base url' => [
            'https://auth.example.com/',
            'master',
            'starts',
            'https://auth.example.com/realms/master/protocol/openid-connect/auth',
        ];
        yield 'whitespace realm' => ['https://auth.example.com', '  myrealm  ', 'contains', '/realms/myrealm'];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function keycloakNonStringConfigProvider(): iterable
    {
        yield 'non-string base url' => [
            ['baseUrl' => ['not', 'a', 'string'], 'realm' => 'testrealm'],
            '/realms/testrealm',
        ];
        yield 'non-string realm' => [
            ['baseUrl' => 'https://auth.example.com', 'realm' => ['not', 'a', 'string']],
            'https://auth.example.com/realms/',
        ];
    }

    public function testCreateWithDisabledAmongEnabled(): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            'github' => [
                'enabled' => true,
                'clientId' => 'gh-id',
                'clientSecret' => 'gh-secret',
            ],
            'facebook' => [
                'enabled' => false,
                'clientId' => 'fb-id',
                'clientSecret' => 'fb-secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        self::assertCount(1, $registry->all());
        self::assertSame('github', $registry->all()[0]->getName());
    }

    public function testCreateWithDisabledFacebook(): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            'facebook' => [
                'enabled' => false,
                'clientId' => 'fb-id',
                'clientSecret' => 'fb-secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        self::assertCount(0, $registry->all());
    }

    /**
     * @param class-string $expectedClass
     */
    #[DataProvider('dedicatedClientProvider')]
    public function testCreateWithEnabledDedicatedClient(string $providerKey, string $expectedClass, string $expectedName): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            $providerKey => [
                'enabled' => true,
                'clientId' => 'id',
                'clientSecret' => 'secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        $clients = $registry->all();
        self::assertCount(1, $clients);
        self::assertInstanceOf($expectedClass, $clients[0]);
        self::assertSame($expectedName, $clients[0]->getName());
    }

    #[DataProvider('genericClientProvider')]
    public function testCreateWithGenericClient(string $providerKey, string $expectedName, string $expectedTitle): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            $providerKey => [
                'enabled' => true,
                'clientId' => 'id',
                'clientSecret' => 'secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        $clients = $registry->all();
        self::assertCount(1, $clients);
        self::assertInstanceOf(GenericAuthClient::class, $clients[0]);
        self::assertSame($expectedName, $clients[0]->getName());
        self::assertSame($expectedTitle, $clients[0]->getTitle());
    }

    public function testCreateWithKeycloak(): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            'keycloak' => [
                'enabled' => true,
                'baseUrl' => 'https://auth.example.com',
                'realm' => 'myrealm',
                'clientId' => 'kc-id',
                'clientSecret' => 'kc-secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        $clients = $registry->all();
        self::assertCount(1, $clients);
        self::assertInstanceOf(GenericAuthClient::class, $clients[0]);
        self::assertSame('keycloak', $clients[0]->getName());
        self::assertSame('Keycloak', $clients[0]->getTitle());

        $url = $clients[0]->getAuthorizationUrl('https://app.com/callback', 'state123');
        self::assertStringContainsString('https://auth.example.com/realms/myrealm/protocol/openid-connect/auth', $url);
    }

    #[DataProvider('keycloakConfigVariantProvider')]
    public function testCreateWithKeycloakConfigVariant(
        ?string $baseUrl,
        ?string $realm,
        string $assertionMode,
        string $expectedUrlFragment,
    ): void {
        $keycloakConfig = ['enabled' => true, 'clientId' => 'id', 'clientSecret' => 'secret'];
        if ($baseUrl !== null) {
            $keycloakConfig['baseUrl'] = $baseUrl;
        }
        if ($realm !== null) {
            $keycloakConfig['realm'] = $realm;
        }

        $config = new ModuleConfig(socialNetworkClients: ['keycloak' => $keycloakConfig]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        $clients = $registry->all();
        $url = $clients[0]->getAuthorizationUrl('https://cb.com', 'state');

        if ($assertionMode === 'starts') {
            self::assertStringStartsWith($expectedUrlFragment, $url);
        } else {
            self::assertStringContainsString($expectedUrlFragment, $url);
        }
    }

    #[DataProvider('keycloakNonStringConfigProvider')]
    public function testCreateWithKeycloakNonStringConfig(array $keycloakOverrides, string $expectedUrlFragment): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            'keycloak' => array_merge(
                ['enabled' => true, 'clientId' => 'id', 'clientSecret' => 'secret'],
                $keycloakOverrides,
            ),
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        $clients = $registry->all();
        $url = $clients[0]->getAuthorizationUrl('https://cb.com', 'state');
        self::assertStringContainsString($expectedUrlFragment, $url);
    }

    public function testCreateWithMultipleClients(): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            'github' => [
                'enabled' => true,
                'clientId' => 'gh-id',
                'clientSecret' => 'gh-secret',
            ],
            'facebook' => [
                'enabled' => true,
                'clientId' => 'fb-id',
                'clientSecret' => 'fb-secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        self::assertCount(2, $registry->all());
    }
    public function testCreateWithNoClients(): void
    {
        $config = new ModuleConfig(socialNetworkClients: []);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        self::assertCount(0, $registry->all());
    }

    public function testCreateWithUnknownProviderReturnsNull(): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            'unknown_provider' => [
                'enabled' => true,
                'clientId' => 'id',
                'clientSecret' => 'secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        self::assertCount(0, $registry->all());
    }

}
