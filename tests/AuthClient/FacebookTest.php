<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\AbstractAuthClient;
use YiiRocks\Voyti\AuthClient\Facebook;

final class FacebookTest extends TestCase
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
        $client = new Facebook($config);
        self::assertSame('facebook', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = new Facebook(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertSame('Facebook', $client->getTitle());
    }

    public function testIsEnabledByDefault(): void
    {
        $client = new Facebook(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertTrue($client->isEnabled());
    }

    public function testIsEnabledWhenDisabled(): void
    {
        $client = new Facebook(['clientId' => 'id', 'clientSecret' => 'secret', 'enabled' => false]);
        self::assertFalse($client->isEnabled());
    }

    public function testIsInstanceOfAbstractAuthClient(): void
    {
        $client = new Facebook(['clientId' => 'id', 'clientSecret' => 'secret']);
        self::assertInstanceOf(AbstractAuthClient::class, $client);
    }

    public function testUserInfoHeadersOverridden(): void
    {
        $client = new Facebook(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'userInfoHeaders');
        $result = $ref->invoke($client, ['access_token' => 'token123']);
        self::assertArrayHasKey('Accept', $result);
        self::assertArrayHasKey('User-Agent', $result);
        self::assertArrayNotHasKey('Authorization', $result);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('userInfoQueryTokenProvider')]
    public function testUserInfoQuery(array $tokenData, string $expectedAccessToken): void
    {
        $client = new Facebook(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'userInfoQuery');
        $result = $ref->invoke($client, $tokenData);
        self::assertSame($expectedAccessToken, $result['access_token']);
        self::assertSame('id,name,email', $result['fields']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function userInfoQueryTokenProvider(): iterable
    {
        yield 'string token' => [['access_token' => 'token123'], 'token123'];
        yield 'missing token' => [[], ''];
        yield 'numeric token' => [['access_token' => 12345], '12345'];
    }
}
