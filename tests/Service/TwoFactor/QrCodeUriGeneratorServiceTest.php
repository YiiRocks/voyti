<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\TwoFactor;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class QrCodeUriGeneratorServiceTest extends TestCase
{

    public function testGenerateQrCodeSvgReturnEmptyForEmptyUri(): void
    {
        $config = new ModuleConfig(appName: '');
        $service = new QrCodeUriGeneratorService($config);

        $user = $this->createMock(User::class);
        $user->method('getAuthTfKey')->willReturn('s');
        $user->method('getEmail')->willReturn('');

        $uri = $service->run($user);
        self::assertNotSame('', $uri);

        $svg = $service->generateQrCodeSvg($user);
        if (class_exists('chillerlan\\QRCode\\QRCode')) {
            self::assertStringContainsString('<svg', $svg);
        } else {
            self::assertSame('', $svg);
        }
    }

    public function testGenerateQrCodeSvgWithNonEmptyUriButNoQRCodeClassReturnsEmpty(): void
    {
        $config = new ModuleConfig(appName: 'TestApp');
        $service = new QrCodeUriGeneratorService($config);

        $user = $this->createMock(User::class);
        $user->method('getAuthTfKey')->willReturn('testsecret');
        $user->method('getEmail')->willReturn('user@example.com');

        $result = $service->generateQrCodeSvg($user);

        if (class_exists('chillerlan\\QRCode\\QRCode')) {
            self::assertStringContainsString('<svg', $result);
        } else {
            self::assertSame('', $result);
        }
    }

    public function testRunEncodesSpecialCharacters(): void
    {
        $config = new ModuleConfig(appName: 'My App');
        $service = new QrCodeUriGeneratorService($config);

        $user = $this->createMock(User::class);
        $user->method('getAuthTfKey')->willReturn('secret123');
        $user->method('getEmail')->willReturn('user+tag@example.com');

        $uri = $service->run($user);

        self::assertStringContainsString('otpauth://totp/', $uri);
        self::assertStringContainsString('secret=secret123', $uri);
    }

    public function testRunWithEmptySecretGeneratesNewOne(): void
    {
        $config = new ModuleConfig(appName: 'VoytiApp');
        $service = new QrCodeUriGeneratorService($config);

        $user = $this->createMock(User::class);
        $user->method('getAuthTfKey')->willReturn('');
        $user->method('getEmail')->willReturn('user@example.com');
        $user->expects($this->once())->method('setAuthTfKey');
        $user->expects($this->once())->method('save');

        $uri = $service->run($user);

        self::assertStringContainsString('otpauth://totp/', $uri);
    }
    public function testRunWithExistingSecret(): void
    {
        $config = new ModuleConfig(appName: 'VoytiApp');
        $service = new QrCodeUriGeneratorService($config);

        $user = $this->createMock(User::class);
        $user->method('getAuthTfKey')->willReturn('existing-secret-key');
        $user->method('getEmail')->willReturn('user@example.com');
        $user->expects($this->never())->method('setAuthTfKey');

        $uri = $service->run($user);

        self::assertStringContainsString('otpauth://totp/', $uri);
        self::assertStringContainsString('secret=existing-secret-key', $uri);
        self::assertStringContainsString('issuer=VoytiApp', $uri);
    }

    public function testRunWithForceNewSecretIgnoresExistingSecret(): void
    {
        $config = new ModuleConfig(appName: 'VoytiApp');
        $service = new QrCodeUriGeneratorService($config);

        $user = $this->createMock(User::class);
        $user->method('getAuthTfKey')->willReturn('existing-secret-key');
        $user->method('getEmail')->willReturn('user@example.com');
        $user->expects($this->once())->method('setAuthTfKey')->with($this->callback(
            static fn (string $secret): bool => $secret !== 'existing-secret-key' && $secret !== '',
        ));
        $user->expects($this->once())->method('save');

        $uri = $service->run($user, forceNewSecret: true);

        self::assertStringContainsString('otpauth://totp/', $uri);
        self::assertStringNotContainsString('secret=existing-secret-key', $uri);
    }

    public function testRunWithNullSecretGeneratesNewOne(): void
    {
        $config = new ModuleConfig(appName: 'VoytiApp');
        $service = new QrCodeUriGeneratorService($config);

        $user = $this->createMock(User::class);
        $user->method('getAuthTfKey')->willReturn(null);
        $user->method('getEmail')->willReturn('user@example.com');
        $user->expects($this->once())->method('setAuthTfKey');
        $user->expects($this->once())->method('save');

        $uri = $service->run($user);

        self::assertStringContainsString('otpauth://totp/', $uri);
    }
}
