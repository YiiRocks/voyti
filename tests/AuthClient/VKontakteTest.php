<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\VKontakte;

final class VKontakteTest extends TestCase
{

    public function testConstructWithoutConfig(): void
    {
        $client = new VKontakte();
        self::assertSame('vkontakte', $client->getName());
    }
    public function testGetName(): void
    {
        $client = new VKontakte(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertSame('vkontakte', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = new VKontakte(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertSame('VKontakte', $client->getTitle());
    }

    public function testIsEnabledByDefault(): void
    {
        $client = new VKontakte(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertTrue($client->isEnabled());
    }

    public function testNormalizeUserAttributesFallsBackToTokenUserId(): void
    {
        $client = new VKontakte(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'normalizeUserAttributes');

        $result = $ref->invoke($client, [
            'response' => [
                ['first_name' => '', 'last_name' => ''],
            ],
        ], [
            'user_id' => 'token_uid',
            'email' => 'user@vk.com',
        ]);

        self::assertSame('token_uid', $result['id']);
        self::assertSame('user@vk.com', $result['email']);
        self::assertNull($result['name']);
    }

    public function testNormalizeUserAttributesWithNullResponse(): void
    {
        $client = new VKontakte(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'normalizeUserAttributes');

        $result = $ref->invoke($client, ['response' => null], []);

        self::assertSame('', $result['id']);
        self::assertNull($result['email']);
        self::assertNull($result['username']);
        self::assertNull($result['name']);
    }

    public function testNormalizeUserAttributesWithPaddedNames(): void
    {
        $client = new VKontakte(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'normalizeUserAttributes');

        $result = $ref->invoke($client, [
            'response' => [
                ['id' => 1, 'first_name' => '  John  ', 'last_name' => '  Doe  '],
            ],
        ], []);

        self::assertNotNull($result['name']);
        self::assertTrue(str_starts_with($result['name'], 'J'), 'Name should not have leading spaces');
        self::assertStringContainsString('John', $result['name']);
        self::assertStringContainsString('Doe', $result['name']);
    }

    public function testNormalizeUserAttributesWithResponse(): void
    {
        $client = new VKontakte(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'normalizeUserAttributes');

        $result = $ref->invoke($client, [
            'response' => [
                ['id' => 12345, 'first_name' => 'Ivan', 'last_name' => 'Petrov', 'screen_name' => 'ivan_p'],
            ],
        ], [
            'email' => 'ivan@vk.com',
            'user_id' => 99999,
        ]);

        self::assertSame('12345', $result['id']);
        self::assertSame('ivan@vk.com', $result['email']);
        self::assertSame('ivan_p', $result['username']);
        self::assertSame('Ivan Petrov', $result['name']);
    }

    public function testNormalizeUserAttributesWithResponseMissingIndexZero(): void
    {
        $client = new VKontakte(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'normalizeUserAttributes');

        $result = $ref->invoke($client, [
            'response' => [1 => ['id' => 1, 'screen_name' => 'test']],
        ], []);

        self::assertSame('', $result['id']);
    }

    public function testUserInfoHeadersOverridden(): void
    {
        $client = new VKontakte(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'userInfoHeaders');

        $result = $ref->invoke($client, []);
        self::assertArrayHasKey('Accept', $result);
        self::assertArrayHasKey('User-Agent', $result);
        self::assertArrayNotHasKey('Authorization', $result);
    }

    public function testUserInfoQueryOverridden(): void
    {
        $client = new VKontakte(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'userInfoQuery');

        $result = $ref->invoke($client, ['access_token' => 'vk_token']);
        self::assertSame('vk_token', $result['access_token']);
        self::assertSame('screen_name', $result['fields']);
        self::assertSame('5.199', $result['v']);
    }

    public function testUserInfoQueryWithEmptyToken(): void
    {
        $client = new VKontakte(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'userInfoQuery');

        $result = $ref->invoke($client, []);
        self::assertSame('', $result['access_token']);
    }

    public function testUserInfoQueryWithNumericToken(): void
    {
        $client = new VKontakte(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'userInfoQuery');

        $result = $ref->invoke($client, ['access_token' => 12345]);
        self::assertSame('12345', $result['access_token']);
    }
}
