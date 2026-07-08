<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\Keycloak;

final class KeycloakTest extends TestCase
{

    public function testConstructWithBaseUrlAndRealm(): void
    {
        $client = new Keycloak([
            'baseUrl' => 'https://auth.example.com/',
            'realm' => 'master',
            'clientId' => 'id',
            'clientSecret' => 'secret',
        ]);
        $ref = new \ReflectionMethod($client, 'clientId');

        self::assertSame('id', $ref->invoke($client));
    }

    public function testConstructWithEmptyBaseUrl(): void
    {
        $client = new Keycloak([
            'baseUrl' => '',
            'realm' => 'test',
            'clientId' => 'id',
            'clientSecret' => 'secret',
        ]);

        self::assertSame('keycloak', $client->getName());
    }

    public function testConstructWithNonStringBaseUrl(): void
    {
        $client = new Keycloak([
            'baseUrl' => ['not', 'a', 'string'],
            'realm' => 'testrealm',
            'clientId' => 'id',
            'clientSecret' => 'secret',
        ]);

        $url = $client->getAuthorizationUrl('https://cb.com', 'state');
        self::assertStringContainsString('/realms/testrealm', $url);
    }

    public function testConstructWithNonStringRealm(): void
    {
        $client = new Keycloak([
            'baseUrl' => 'https://auth.example.com',
            'realm' => ['not', 'a', 'string'],
            'clientId' => 'id',
            'clientSecret' => 'secret',
        ]);

        $url = $client->getAuthorizationUrl('https://cb.com', 'state');
        self::assertStringContainsString('https://auth.example.com/realms/', $url);
    }

    public function testConstructWithoutBaseUrl(): void
    {
        $client = new Keycloak([
            'realm' => 'testrealm',
            'clientId' => 'id',
            'clientSecret' => 'secret',
        ]);

        $url = $client->getAuthorizationUrl('https://cb.com', 'state');
        self::assertStringStartsWith('/realms/testrealm/protocol/openid-connect/auth', $url);
    }

    public function testConstructWithoutRealm(): void
    {
        $client = new Keycloak([
            'baseUrl' => 'https://auth.example.com',
            'clientId' => 'id',
            'clientSecret' => 'secret',
        ]);

        $url = $client->getAuthorizationUrl('https://cb.com', 'state');
        self::assertStringStartsWith('https://auth.example.com/realms/', $url);
    }

    public function testConstructWithTrailingSlashBaseUrl(): void
    {
        $client = new Keycloak([
            'baseUrl' => 'https://auth.example.com/',
            'realm' => 'master',
            'clientId' => 'id',
            'clientSecret' => 'secret',
        ]);
        $authUrlRef = new \ReflectionMethod($client, 'getAuthorizationUrl');

        $url = $authUrlRef->invoke($client, 'https://fallback.com/callback', 'random_state');
        self::assertStringStartsWith('https://auth.example.com/realms/master/protocol/openid-connect/auth', $url);
    }

    public function testConstructWithWhitespaceRealm(): void
    {
        $client = new Keycloak([
            'baseUrl' => 'https://auth.example.com',
            'realm' => '  myrealm  ',
            'clientId' => 'id',
            'clientSecret' => 'secret',
        ]);

        $url = $client->getAuthorizationUrl('https://cb.com', 'state');
        self::assertStringContainsString('/realms/myrealm', $url);
    }

    public function testGetAuthorizationUrlContainsCorrectBase(): void
    {
        $client = new Keycloak([
            'baseUrl' => 'https://sso.corp.com',
            'realm' => 'internal',
            'clientId' => 'app-client',
            'clientSecret' => 'secret',
        ]);

        $url = $client->getAuthorizationUrl('https://app.com/callback', 'state123');
        self::assertStringContainsString('sso.corp.com/realms/internal/protocol/openid-connect/auth', $url);
    }
    public function testGetName(): void
    {
        $client = new Keycloak([
            'baseUrl' => 'https://auth.example.com',
            'realm' => 'myrealm',
            'clientId' => 'id',
            'clientSecret' => 'secret',
        ]);
        self::assertSame('keycloak', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = new Keycloak([
            'baseUrl' => 'https://auth.example.com',
            'realm' => 'myrealm',
            'clientId' => 'id',
            'clientSecret' => 'secret',
        ]);
        self::assertSame('Keycloak', $client->getTitle());
    }

    public function testIsEnabledByDefault(): void
    {
        $client = new Keycloak([
            'baseUrl' => 'https://auth.example.com',
            'realm' => 'myrealm',
            'clientId' => 'id',
            'clientSecret' => 'secret',
        ]);
        self::assertTrue($client->isEnabled());
    }
}
