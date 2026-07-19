<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\LoginMetadataHelper;

final class LoginMetadataHelperTest extends TestCase
{
    public function testRemoteAddrFallsBackToLocalhostWhenEmpty(): void
    {
        self::assertSame('127.0.0.1', LoginMetadataHelper::remoteAddr(['REMOTE_ADDR' => '']));
    }

    public function testRemoteAddrFallsBackToLocalhostWhenMissing(): void
    {
        self::assertSame('127.0.0.1', LoginMetadataHelper::remoteAddr([]));
    }

    public function testRemoteAddrFallsBackToLocalhostWhenNotString(): void
    {
        self::assertSame('127.0.0.1', LoginMetadataHelper::remoteAddr(['REMOTE_ADDR' => 12345]));
    }

    public function testRemoteAddrReturnsValue(): void
    {
        self::assertSame('203.0.113.9', LoginMetadataHelper::remoteAddr(['REMOTE_ADDR' => '203.0.113.9']));
    }

    public function testUserAgentReturnsNullWhenEmpty(): void
    {
        self::assertNull(LoginMetadataHelper::userAgent(['HTTP_USER_AGENT' => '']));
    }

    public function testUserAgentReturnsNullWhenMissing(): void
    {
        self::assertNull(LoginMetadataHelper::userAgent([]));
    }

    public function testUserAgentReturnsNullWhenNotString(): void
    {
        self::assertNull(LoginMetadataHelper::userAgent(['HTTP_USER_AGENT' => 12345]));
    }

    public function testUserAgentReturnsValue(): void
    {
        self::assertSame('TestAgent', LoginMetadataHelper::userAgent(['HTTP_USER_AGENT' => 'TestAgent']));
    }
}
