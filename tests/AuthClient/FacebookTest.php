<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\AbstractAuthClient;
use YiiRocks\Voyti\AuthClient\Facebook;

final class FacebookTest extends TestCase
{

    public function testConstructWithoutConfig(): void
    {
        $client = new Facebook();
        self::assertSame('facebook', $client->getName());
    }
    public function testGetName(): void
    {
        $client = new Facebook(['clientId' => 'id', 'clientSecret' => 'secret']);
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

    public function testUserInfoQueryOverridden(): void
    {
        $client = new Facebook(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'userInfoQuery');
        $result = $ref->invoke($client, ['access_token' => 'token123']);
        self::assertSame('token123', $result['access_token']);
        self::assertSame('id,name,email', $result['fields']);
    }

    public function testUserInfoQueryWithEmptyToken(): void
    {
        $client = new Facebook(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'userInfoQuery');
        $result = $ref->invoke($client, []);
        self::assertSame('', $result['access_token']);
        self::assertSame('id,name,email', $result['fields']);
    }

    public function testUserInfoQueryWithNumericToken(): void
    {
        $client = new Facebook(['clientId' => 'id', 'clientSecret' => 'secret']);
        $ref = new \ReflectionMethod($client, 'userInfoQuery');
        $result = $ref->invoke($client, ['access_token' => 12345]);
        self::assertSame('12345', $result['access_token']);
        self::assertSame('id,name,email', $result['fields']);
    }
}
