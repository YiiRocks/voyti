<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\AuthClientRegistryFactory;
use YiiRocks\Voyti\AuthClient\Facebook;
use YiiRocks\Voyti\AuthClient\GitHub;
use YiiRocks\Voyti\AuthClient\Google;
use YiiRocks\Voyti\AuthClient\Keycloak;
use YiiRocks\Voyti\AuthClient\LinkedIn;
use YiiRocks\Voyti\AuthClient\Microsoft365;
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
        self::assertInstanceOf(Google::class, $registry->get('google'));
        self::assertInstanceOf(Keycloak::class, $registry->get('keycloak'));
        self::assertInstanceOf(LinkedIn::class, $registry->get('linkedin'));
        self::assertInstanceOf(Microsoft365::class, $registry->get('microsoft365'));
        self::assertInstanceOf(VKontakte::class, $registry->get('vkontakte'));
        self::assertInstanceOf(Twitter::class, $registry->get('x'));
        self::assertInstanceOf(Yandex::class, $registry->get('yandex'));
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
}
