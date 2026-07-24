<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\TwoFactor;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;

#[AllowMockObjectsWithoutExpectations]
final class QrCodeUriGeneratorServiceTest extends TestCase
{
    public function testGenerateQrCodeSvgReturnsSvgForExistingSecret(): void
    {
        $config = ModuleConfigFactory::create(appName: '');
        $service = new QrCodeUriGeneratorService($config);

        $user = $this->createMock(User::class);
        $user->method('getAuthTfKey')->willReturn('s');
        $user->method('getEmail')->willReturn('');

        $uri = $service->run($user);
        self::assertNotSame('', $uri);

        $svg = $service->generateQrCodeSvg($user);
        self::assertStringContainsString('<svg', $svg);
    }

    public function testIsAvailableReturnsTrueWhenBothLibrariesAreInstalled(): void
    {
        $service = new QrCodeUriGeneratorService(ModuleConfigFactory::create());

        self::assertTrue($service->isAvailable());
    }

    public function testRegenerateIgnoresExistingSecret(): void
    {
        $config = ModuleConfigFactory::create(appName: 'VoytiApp');
        $service = new QrCodeUriGeneratorService($config);

        $user = $this->createMock(User::class);
        $user->method('getAuthTfKey')->willReturn('existing-secret-key');
        $user->method('getEmail')->willReturn('user@example.com');
        $user->expects($this->once())->method('setAuthTfKey')->with($this->callback(
            static fn(string $secret): bool => $secret !== 'existing-secret-key' && $secret !== '',
        ));
        $user->expects($this->once())->method('save');

        $uri = $service->regenerate($user);

        self::assertStringContainsString('otpauth://totp/', $uri);
        self::assertStringNotContainsString('secret=existing-secret-key', $uri);
    }

    public function testRegenerateQrCodeSvgIgnoresExistingSecret(): void
    {
        $config = ModuleConfigFactory::create(appName: 'TestApp');
        $service = new QrCodeUriGeneratorService($config);

        $user = $this->createMock(User::class);
        $user->method('getAuthTfKey')->willReturn('existing-secret-key');
        $user->method('getEmail')->willReturn('user@example.com');
        $user->expects($this->once())->method('setAuthTfKey')->with($this->callback(
            static fn(string $secret): bool => $secret !== 'existing-secret-key' && $secret !== '',
        ));
        $user->expects($this->once())->method('save');

        $result = $service->regenerateQrCodeSvg($user);

        self::assertStringContainsString('<svg', $result);
    }

    public function testRunEncodesSpecialCharacters(): void
    {
        $config = ModuleConfigFactory::create(appName: 'My App');
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
        $config = ModuleConfigFactory::create(appName: 'VoytiApp');
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
        $config = ModuleConfigFactory::create(appName: 'VoytiApp');
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

    public function testRunWithNullSecretGeneratesNewOne(): void
    {
        $config = ModuleConfigFactory::create(appName: 'VoytiApp');
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
