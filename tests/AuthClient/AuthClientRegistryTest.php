<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\AuthClient\Facebook;
use YiiRocks\Voyti\AuthClient\GitHub;
use YiiRocks\Voyti\AuthClient\Google;
use YiiRocks\Voyti\AuthClient\Keycloak;
use YiiRocks\Voyti\AuthClient\LinkedIn;
use YiiRocks\Voyti\AuthClient\Microsoft365;
use YiiRocks\Voyti\AuthClient\Twitter;
use YiiRocks\Voyti\AuthClient\VKontakte;
use YiiRocks\Voyti\AuthClient\Yandex;

final class AuthClientRegistryTest extends TestCase
{
    public function testAllClientsAreRegistered(): void
    {
        $registry = new AuthClientRegistry(
            new Facebook(),
            new GitHub(),
            new Google(),
            new Keycloak(),
            new LinkedIn(),
            new Microsoft365(),
            new Twitter(),
            new VKontakte(),
            new Yandex(),
        );

        $clients = $registry->all();

        self::assertCount(9, $clients);
        self::assertSame('facebook', $clients[0]->getName());
        self::assertSame('github', $clients[1]->getName());
        self::assertSame('google', $clients[2]->getName());
        self::assertSame('keycloak', $clients[3]->getName());
        self::assertSame('linkedin', $clients[4]->getName());
        self::assertSame('microsoft365', $clients[5]->getName());
        self::assertSame('vkontakte', $clients[7]->getName());
        self::assertSame('x', $clients[6]->getName());
        self::assertSame('yandex', $clients[8]->getName());
    }

    public function testGetReturnsClientByName(): void
    {
        $registry = new AuthClientRegistry(new GitHub());

        $client = $registry->get('github');

        self::assertInstanceOf(GitHub::class, $client);
        self::assertSame('GitHub', $client?->getTitle());
        self::assertNull($registry->get('missing'));
    }
}
