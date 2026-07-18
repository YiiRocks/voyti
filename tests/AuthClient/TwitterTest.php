<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\Twitter;

final class TwitterTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function constructionProvider(): iterable
    {
        yield 'without config' => [[]];
        yield 'with config' => [['clientId' => 'id', 'clientSecret' => 'secret']];
    }

    #[DataProvider('constructionProvider')]
    public function testGetName(array $config): void
    {
        $client = new Twitter($config);
        self::assertSame('x', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = new Twitter(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertSame('X (formerly Twitter)', $client->getTitle());
    }

    public function testIsEnabledByDefault(): void
    {
        $client = new Twitter(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertTrue($client->isEnabled());
    }

    public function testNormalizeUserAttributesReturnsEmptyIdWhenMissingData(): void
    {
        $client = new Twitter(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'normalizeUserAttributes');

        $result = $ref->invoke($client, [], []);

        self::assertSame('', $result['id']);
        self::assertNull($result['email']);
        self::assertNull($result['username']);
        self::assertNull($result['name']);
    }

    public function testNormalizeUserAttributesReturnsIdFromData(): void
    {
        $client = new Twitter(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'normalizeUserAttributes');

        $result = $ref->invoke($client, ['data' => ['id' => '12345', 'username' => 'jack', 'name' => 'Jack']], []);

        self::assertSame('12345', $result['id']);
        self::assertNull($result['email']);
        self::assertSame('jack', $result['username']);
        self::assertSame('Jack', $result['name']);
    }

    public function testNormalizeUserAttributesWithMissingDataKey(): void
    {
        $client = new Twitter(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'normalizeUserAttributes');

        $result = $ref->invoke($client, ['data' => 'not_an_array'], []);

        self::assertSame('', $result['id']);
    }

    public function testUserInfoQueryOverridden(): void
    {
        $client = new Twitter(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'userInfoQuery');

        $result = $ref->invoke($client, []);

        self::assertSame('id,name,username,profile_image_url', $result['user.fields']);
    }
}
