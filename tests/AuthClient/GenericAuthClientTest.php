<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\AuthClient;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\AuthClient\GenericAuthClient;

final class GenericAuthClientTest extends TestCase
{

    public function testCustomScopeOverridesDefault(): void
    {
        $client = new GenericAuthClient(
            'custom',
            'Custom Provider',
            'https://example.com/auth',
            'https://example.com/token',
            'https://example.com/userinfo',
            'default_scope',
            ['scope' => 'custom_scope'],
        );
        $ref = new \ReflectionMethod($client, 'scope');
        self::assertSame('custom_scope', $ref->invoke($client));
    }

    public function testDefaultScopeUsedWhenNotInConfig(): void
    {
        $client = new GenericAuthClient(
            'custom',
            'Custom Provider',
            'https://example.com/auth',
            'https://example.com/token',
            'https://example.com/userinfo',
            'default_scope',
            ['clientId' => 'id', 'clientSecret' => 'secret'],
        );
        $ref = new \ReflectionMethod($client, 'scope');
        self::assertSame('default_scope', $ref->invoke($client));
    }
    public function testGetName(): void
    {
        $client = new GenericAuthClient(
            'custom',
            'Custom Provider',
            'https://example.com/auth',
            'https://example.com/token',
            'https://example.com/userinfo',
            'email',
            ['clientId' => 'id', 'clientSecret' => 'secret'],
        );
        self::assertSame('custom', $client->getName());
    }

    public function testGetTitle(): void
    {
        $client = new GenericAuthClient(
            'custom',
            'Custom Provider',
            'https://example.com/auth',
            'https://example.com/token',
            'https://example.com/userinfo',
            'email',
            ['clientId' => 'id', 'clientSecret' => 'secret'],
        );
        self::assertSame('Custom Provider', $client->getTitle());
    }

    public function testIsEnabledByDefault(): void
    {
        $client = new GenericAuthClient(
            'custom',
            'Custom Provider',
            'https://example.com/auth',
            'https://example.com/token',
            'https://example.com/userinfo',
            '',
        );
        self::assertTrue($client->isEnabled());
    }

    public function testIsEnabledCanBeDisabled(): void
    {
        $client = new GenericAuthClient(
            'custom',
            'Custom Provider',
            'https://example.com/auth',
            'https://example.com/token',
            'https://example.com/userinfo',
            '',
            ['enabled' => false],
        );
        self::assertFalse($client->isEnabled());
    }
}
