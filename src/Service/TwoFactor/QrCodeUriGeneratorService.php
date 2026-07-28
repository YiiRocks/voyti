<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\TwoFactor;

use chillerlan\TwoFactorQRCode\TwoFactorQRCode;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;

/**
 * Builds QR-code SVGs for two-factor authentication setup. `generateQrCodeSvg()` reuses the
 * user's existing {@see User::getAuthTfKey()} secret if present, while `regenerateQrCodeSvg()`
 * always issues a fresh secret. Every method here assumes {@see isAvailable()} has already been
 * checked by the caller.
 */
final readonly class QrCodeUriGeneratorService
{
    public function __construct(
        private ModuleConfig $config,
    ) {}

    public function generateQrCodeSvg(User $user): string
    {
        return $this->buildSvg($user, $user->getAuthTfKey());
    }

    public function isAvailable(): bool
    {
        return class_exists(TwoFactorQRCode::class);
    }

    public function regenerateQrCodeSvg(User $user): string
    {
        return $this->buildSvg($user, null);
    }

    private function buildSvg(User $user, ?string $secret): string
    {
        $secret = $this->resolveSecret($user, $secret);

        $twoFactorQrCode = new TwoFactorQRCode(['outputBase64' => false, 'connectPaths' => true, 'scale' => 4]);
        $twoFactorQrCode->setSecret($secret);

        return $twoFactorQrCode->getQRCode($user->getEmail(), $this->config->appName);
    }

    private function resolveSecret(User $user, ?string $secret): string
    {
        if ($secret === null || $secret === '') {
            $secret = (new TwoFactorQRCode())->createSecret();
            $user->setAuthTfKey($secret);
            $user->save();
        }

        return $secret;
    }
}
