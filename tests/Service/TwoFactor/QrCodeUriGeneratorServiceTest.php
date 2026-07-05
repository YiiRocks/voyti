<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\TwoFactor;

use chillerlan\QRCode\QRCode;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;

final class QrCodeUriGeneratorServiceTest extends TestCase
{
    public function testGenerateQrCodeSvgRendersAtScaleFourAsRawSvg(): void
    {
        $user = $this->createUserWithSecret();
        $service = new QrCodeUriGeneratorService(ModuleConfig::fromArray([]));

        $svg = $service->generateQrCodeSvg($user);
        $uri = $service->run($user);

        $expected = (string) (new QRCode(['scale' => 4, 'outputBase64' => false, 'connectPaths' => true]))->render($uri);
        $withoutConnectPaths = (string) (new QRCode(['scale' => 4, 'outputBase64' => false, 'connectPaths' => false]))->render($uri);

        self::assertSame($expected, $svg);
        self::assertNotSame($withoutConnectPaths, $svg);
    }

    public function testRunReusesExistingSecretAndBuildsOtpauthUri(): void
    {
        $user = $this->createUserWithSecret();
        $service = new QrCodeUriGeneratorService(ModuleConfig::fromArray([]));

        $uri = $service->run($user);

        self::assertSame(
            'otpauth://totp/Voyti:qr%40example.com?secret=ABCDEFGHIJKLMNOP&issuer=Voyti',
            $uri,
        );
    }

    private function createUserWithSecret(): User
    {
        $user = new User();
        $user->setEmail('qr@example.com');
        $user->setAuthTfKey('ABCDEFGHIJKLMNOP');

        return $user;
    }
}
