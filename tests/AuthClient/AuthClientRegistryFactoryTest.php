<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\AuthClientRegistryFactory;
use YiiRocks\Voyti\AuthClient\Facebook;
use YiiRocks\Voyti\AuthClient\GenericAuthClient;
use YiiRocks\Voyti\AuthClient\GitHub;
use YiiRocks\Voyti\AuthClient\Keycloak;
use YiiRocks\Voyti\AuthClient\Twitter;
use YiiRocks\Voyti\AuthClient\VKontakte;
use YiiRocks\Voyti\AuthClient\Yandex;
use YiiRocks\Voyti\ModuleConfig;

final class AuthClientRegistryFactoryTest extends TestCase
{

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

    public function testCreateWithEnabledFacebook(): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            'facebook' => [
                'enabled' => true,
                'clientId' => 'fb-id',
                'clientSecret' => 'fb-secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        $clients = $registry->all();
        self::assertCount(1, $clients);
        self::assertInstanceOf(Facebook::class, $clients[0]);
        self::assertSame('facebook', $clients[0]->getName());
    }

    public function testCreateWithEnabledGitHub(): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            'github' => [
                'enabled' => true,
                'clientId' => 'gh-id',
                'clientSecret' => 'gh-secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        $clients = $registry->all();
        self::assertCount(1, $clients);
        self::assertInstanceOf(GitHub::class, $clients[0]);
        self::assertSame('github', $clients[0]->getName());
    }

    public function testCreateWithGenericGoogle(): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            'google' => [
                'enabled' => true,
                'clientId' => 'goog-id',
                'clientSecret' => 'goog-secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        $clients = $registry->all();
        self::assertCount(1, $clients);
        self::assertInstanceOf(GenericAuthClient::class, $clients[0]);
        self::assertSame('google', $clients[0]->getName());
        self::assertSame('Google', $clients[0]->getTitle());
    }

    public function testCreateWithGenericLinkedin(): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            'linkedin' => [
                'enabled' => true,
                'clientId' => 'li-id',
                'clientSecret' => 'li-secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        $clients = $registry->all();
        self::assertCount(1, $clients);
        self::assertInstanceOf(GenericAuthClient::class, $clients[0]);
        self::assertSame('linkedin', $clients[0]->getName());
        self::assertSame('LinkedIn', $clients[0]->getTitle());
    }

    public function testCreateWithGenericMicrosoft365(): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            'microsoft365' => [
                'enabled' => true,
                'clientId' => 'ms-id',
                'clientSecret' => 'ms-secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        $clients = $registry->all();
        self::assertCount(1, $clients);
        self::assertInstanceOf(GenericAuthClient::class, $clients[0]);
        self::assertSame('microsoft365', $clients[0]->getName());
        self::assertSame('Microsoft 365', $clients[0]->getTitle());
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
        self::assertInstanceOf(Keycloak::class, $clients[0]);
        self::assertSame('keycloak', $clients[0]->getName());
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

    public function testCreateWithTwitter(): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            'x' => [
                'enabled' => true,
                'clientId' => 'tw-id',
                'clientSecret' => 'tw-secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        $clients = $registry->all();
        self::assertCount(1, $clients);
        self::assertInstanceOf(Twitter::class, $clients[0]);
        self::assertSame('x', $clients[0]->getName());
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

    public function testCreateWithVKontakte(): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            'vkontakte' => [
                'enabled' => true,
                'clientId' => 'vk-id',
                'clientSecret' => 'vk-secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        $clients = $registry->all();
        self::assertCount(1, $clients);
        self::assertInstanceOf(VKontakte::class, $clients[0]);
        self::assertSame('vkontakte', $clients[0]->getName());
    }

    public function testCreateWithYandex(): void
    {
        $config = new ModuleConfig(socialNetworkClients: [
            'yandex' => [
                'enabled' => true,
                'clientId' => 'ya-id',
                'clientSecret' => 'ya-secret',
            ],
        ]);
        $factory = new AuthClientRegistryFactory($config);
        $registry = $factory->create();

        $clients = $registry->all();
        self::assertCount(1, $clients);
        self::assertInstanceOf(Yandex::class, $clients[0]);
        self::assertSame('yandex', $clients[0]->getName());
    }
}
