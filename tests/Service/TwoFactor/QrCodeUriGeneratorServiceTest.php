<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\TwoFactor;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;

#[AllowMockObjectsWithoutExpectations]
final class QrCodeUriGeneratorServiceTest extends TestCase
{
    public function testGenerateQrCodeSvgRegeneratesSecretWhenStoredValueIsNotValidBase32(): void
    {
        $config = VoytiConfigFactory::create(appName: 'VoytiApp');
        $service = new QrCodeUriGeneratorService($config);

        $user = $this->createMock(User::class);
        $user->method('getAuthTfKey')->willReturn('190812');
        $user->method('getEmail')->willReturn('user@example.com');
        $user->expects($this->once())->method('setAuthTfKey')->with($this->callback(
            static fn(string $secret): bool => $secret !== '190812' && $secret !== '',
        ));
        $user->expects($this->once())->method('save');

        $svg = $service->generateQrCodeSvg($user);
        self::assertStringContainsString('<svg', $svg);
    }

    public function testGenerateQrCodeSvgReturnsSvgForExistingSecret(): void
    {
        $config = VoytiConfigFactory::create(appName: 'VoytiApp');
        $service = new QrCodeUriGeneratorService($config);

        $user = $this->createMock(User::class);
        $user->method('getAuthTfKey')->willReturn('JBSWY3DPEHPK3PXP');
        $user->method('getEmail')->willReturn('user@example.com');
        $user->expects($this->never())->method('setAuthTfKey');

        $svg = $service->generateQrCodeSvg($user);
        self::assertStringContainsString('<svg', $svg);
    }

    public function testIsAvailableReturnsTrueWhenBothLibrariesAreInstalled(): void
    {
        $service = new QrCodeUriGeneratorService(VoytiConfigFactory::create());

        self::assertTrue($service->isAvailable());
    }

    public function testRegenerateQrCodeSvgIgnoresExistingSecret(): void
    {
        $config = VoytiConfigFactory::create(appName: 'TestApp');
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
}
