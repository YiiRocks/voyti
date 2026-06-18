<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\GravatarHelper;

final class GravatarHelperTest extends TestCase
{
    private GravatarHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new GravatarHelper();
    }

    public function testBuildIdReturnsMd5HashOfEmail(): void
    {
        $email = 'test@example.com';
        $expectedHash = md5(strtolower(trim($email)));
        self::assertSame($expectedHash, $this->helper->buildId($email));
    }

    public function testBuildIdIsCaseInsensitive(): void
    {
        self::assertSame(
            $this->helper->buildId('Test@Example.COM'),
            $this->helper->buildId('test@example.com'),
        );
    }

    public function testBuildIdTrimsWhitespace(): void
    {
        self::assertSame(
            $this->helper->buildId('  test@example.com  '),
            $this->helper->buildId('test@example.com'),
        );
    }

    public function testBuildIdWithDifferentEmailsReturnsDifferentHashes(): void
    {
        $hash1 = $this->helper->buildId('alice@example.com');
        $hash2 = $this->helper->buildId('bob@example.com');
        self::assertNotSame($hash1, $hash2);
    }

    public function testGetUrlReturnsHttpsUrl(): void
    {
        $gravatarId = md5('test@example.com');
        $url = $this->helper->getUrl($gravatarId);
        self::assertStringStartsWith('https://', $url);
    }

    public function testGetUrlContainsGravatarBaseUrl(): void
    {
        $gravatarId = md5('test@example.com');
        $url = $this->helper->getUrl($gravatarId);
        self::assertStringContainsString('www.gravatar.com/avatar/', $url);
    }

    public function testGetUrlContainsGravatarId(): void
    {
        $gravatarId = md5('test@example.com');
        $url = $this->helper->getUrl($gravatarId);
        self::assertStringContainsString($gravatarId, $url);
    }

    public function testGetUrlContainsSizeParameter(): void
    {
        $gravatarId = md5('test@example.com');
        $url = $this->helper->getUrl($gravatarId, 100);
        self::assertStringContainsString('s=100', $url);
    }

    public function testGetUrlDefaultSizeIs200(): void
    {
        $gravatarId = md5('test@example.com');
        $url = $this->helper->getUrl($gravatarId);
        self::assertStringContainsString('s=200', $url);
    }

    public function testGetUrlContainsIdenticonDefault(): void
    {
        $gravatarId = md5('test@example.com');
        $url = $this->helper->getUrl($gravatarId);
        self::assertStringContainsString('d=identicon', $url);
    }

    public function testFullUrlFormatMatchesGravatarSpec(): void
    {
        $email = 'user@example.com';
        $gravatarId = $this->helper->buildId($email);
        $url = $this->helper->getUrl($gravatarId, 80);

        $expected = 'https://www.gravatar.com/avatar/' . $gravatarId . '?s=80&d=identicon';
        self::assertSame($expected, $url);
    }

    public function testBuildIdAndGetUrlIntegration(): void
    {
        $email = 'alice@example.com';
        $gravatarId = $this->helper->buildId($email);
        $url = $this->helper->getUrl($gravatarId);

        self::assertStringStartsWith('https://www.gravatar.com/avatar/', $url);
        self::assertStringContainsString($gravatarId, $url);
    }
}
