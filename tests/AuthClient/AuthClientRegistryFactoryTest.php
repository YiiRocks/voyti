<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use YiiRocks\Voyti\AuthClient\AbstractAuthClient;
use YiiRocks\Voyti\AuthClient\AuthClientInterface;
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
    public function testCreateBuildsRegistryForEverySupportedProvider(): void
    {
        $factory = new AuthClientRegistryFactory(new ModuleConfig(
            socialNetworkClients: [
                'facebook' => ['enabled' => true],
                'github' => ['enabled' => true],
                'google' => ['enabled' => true],
                'keycloak' => ['enabled' => true],
                'linkedin' => ['enabled' => true],
                'microsoft365' => ['enabled' => true],
                'vkontakte' => ['enabled' => true],
                'x' => ['enabled' => true],
                'yandex' => ['enabled' => true],
            ],
        ));

        $registry = $factory->create();

        self::assertTrue((new \ReflectionMethod(AuthClientRegistryFactory::class, 'create'))->isPublic());
        self::assertCount(9, $registry->all());
        self::assertInstanceOf(Facebook::class, $registry->get('facebook'));
        self::assertInstanceOf(GitHub::class, $registry->get('github'));
        self::assertInstanceOf(Keycloak::class, $registry->get('keycloak'));
        self::assertInstanceOf(VKontakte::class, $registry->get('vkontakte'));
        self::assertInstanceOf(Twitter::class, $registry->get('x'));
        self::assertInstanceOf(Yandex::class, $registry->get('yandex'));

        $this->assertGenericProvider(
            $registry->get('google'),
            'google',
            'Google',
            'https://accounts.google.com/o/oauth2/v2/auth',
            'https://oauth2.googleapis.com/token',
            'https://openidconnect.googleapis.com/v1/userinfo',
            'openid email profile',
        );
        $this->assertGenericProvider(
            $registry->get('linkedin'),
            'linkedin',
            'LinkedIn',
            'https://www.linkedin.com/oauth/v2/authorization',
            'https://www.linkedin.com/oauth/v2/accessToken',
            'https://api.linkedin.com/v2/userinfo',
            'openid profile email',
        );
        $this->assertGenericProvider(
            $registry->get('microsoft365'),
            'microsoft365',
            'Microsoft 365',
            'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'https://graph.microsoft.com/oidc/userinfo',
            'openid profile email User.Read',
        );
    }

    public function testCreateSkipsDisabledAndUnknownProviders(): void
    {
        $factory = new AuthClientRegistryFactory(new ModuleConfig(
            socialNetworkClients: [
                'github' => ['enabled' => true],
                'google' => ['enabled' => false],
                'unknown' => ['enabled' => true],
            ],
        ));

        $registry = $factory->create();

        self::assertCount(1, $registry->all());
        self::assertInstanceOf(GitHub::class, $registry->get('github'));
        self::assertNull($registry->get('google'));
        self::assertNull($registry->get('unknown'));
    }

    private function assertGenericProvider(
        ?AuthClientInterface $client,
        string $name,
        string $title,
        string $authUrl,
        string $tokenUrl,
        string $userInfoUrl,
        string $scope,
    ): void {
        self::assertInstanceOf(GenericAuthClient::class, $client);
        self::assertSame($name, $client->getName());
        self::assertSame($title, $client->getTitle());

        $reflection = new ReflectionClass(AbstractAuthClient::class);
        self::assertSame($authUrl, $reflection->getProperty('authUrl')->getValue($client));
        self::assertSame($tokenUrl, $reflection->getProperty('tokenUrl')->getValue($client));
        self::assertSame($userInfoUrl, $reflection->getProperty('userInfoUrl')->getValue($client));
        self::assertSame($scope, $reflection->getProperty('scope')->getValue($client));
    }
}
