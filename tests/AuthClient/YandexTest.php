<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\Yandex;

final class YandexTest extends TestCase
{

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function constructionProvider(): iterable
    {
        yield 'without config' => [[]];
        yield 'with config' => [['clientId' => 'id', 'clientSecret' => 'secret']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('constructionProvider')]
    public function testGetName(array $config): void
    {
        $client = new Yandex($config);
        self::assertSame('yandex', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = new Yandex(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertSame('Yandex', $client->getTitle());
    }

    public function testIsEnabledByDefault(): void
    {
        $client = new Yandex(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertTrue($client->isEnabled());
    }

    public function testNormalizeUserAttributesFallsBackToEmail(): void
    {
        $client = new Yandex(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'normalizeUserAttributes');

        $result = $ref->invoke($client, [
            'id' => '123',
            'email' => 'alt@yandex.ru',
            'display_name' => 'display_user',
        ], []);

        self::assertSame('123', $result['id']);
        self::assertSame('alt@yandex.ru', $result['email']);
        self::assertSame('display_user', $result['username']);
        self::assertSame('display_user', $result['name']);
    }

    public function testNormalizeUserAttributesWithDefaultEmail(): void
    {
        $client = new Yandex(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'normalizeUserAttributes');

        $result = $ref->invoke($client, [
            'id' => 'yandex_uid',
            'default_email' => 'user@yandex.ru',
            'login' => 'yandex_user',
            'real_name' => 'Ivan Petrov',
        ], []);

        self::assertSame('yandex_uid', $result['id']);
        self::assertSame('user@yandex.ru', $result['email']);
        self::assertSame('yandex_user', $result['username']);
        self::assertSame('Ivan Petrov', $result['name']);
    }

    public function testNormalizeUserAttributesWithLoginAndDisplayName(): void
    {
        $client = new Yandex(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'normalizeUserAttributes');

        $result = $ref->invoke($client, [
            'id' => '1',
            'login' => 'mylogin',
            'display_name' => 'My Display',
        ], []);

        self::assertSame('mylogin', $result['username']);
        self::assertSame('My Display', $result['name']);
    }

    public function testNormalizeUserAttributesWithMissingData(): void
    {
        $client = new Yandex(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'normalizeUserAttributes');

        $result = $ref->invoke($client, [], []);

        self::assertSame('', $result['id']);
        self::assertNull($result['email']);
        self::assertNull($result['username']);
        self::assertNull($result['name']);
    }

    public function testUserInfoQueryOverridden(): void
    {
        $client = new Yandex(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'userInfoQuery');

        $result = $ref->invoke($client, []);

        self::assertSame('json', $result['format']);
    }
}
