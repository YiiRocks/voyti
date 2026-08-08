<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\TwoFactor;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\TwoFactor\QrCodeUriGeneratorService;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;

#[AllowMockObjectsWithoutExpectations]
final class QrCodeUriGeneratorServiceTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public function testGenerateQrCodeSvgRegeneratesSecretWhenStoredValueIsNotValidBase32(): void
    {
        $config = VoytiConfigFactory::create(appName: 'VoytiApp');
        $service = new QrCodeUriGeneratorService($config);

        $user = $this->createUser(email: 'user@example.com', authTfKey: '190812');

        $svg = $service->generateQrCodeSvg($user);

        self::assertStringContainsString('<svg', $svg);
        $secret = $user->getAuthTfKey();
        self::assertNotSame('190812', $secret);
        self::assertNotSame('', $secret);
        self::assertSame($secret, User::findById((int) $user->getId())?->getAuthTfKey());
    }

    public function testGenerateQrCodeSvgReturnsSvgForExistingSecret(): void
    {
        $config = VoytiConfigFactory::create(appName: 'VoytiApp');
        $service = new QrCodeUriGeneratorService($config);

        $user = $this->createUser(email: 'user@example.com', authTfKey: 'JBSWY3DPEHPK3PXP');

        $svg = $service->generateQrCodeSvg($user);

        self::assertStringContainsString('<svg', $svg);
        self::assertSame('JBSWY3DPEHPK3PXP', $user->getAuthTfKey());
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

        $user = $this->createUser(email: 'user@example.com', authTfKey: 'existing-secret-key');

        $result = $service->regenerateQrCodeSvg($user);

        self::assertStringContainsString('<svg', $result);
        $secret = $user->getAuthTfKey();
        self::assertNotSame('existing-secret-key', $secret);
        self::assertNotSame('', $secret);
        self::assertSame($secret, User::findById((int) $user->getId())?->getAuthTfKey());
    }
}
