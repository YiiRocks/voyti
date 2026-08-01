<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\TwoFactor;

use chillerlan\TwoFactorQRCode\TwoFactorQRCode;
use InvalidArgumentException;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\VoytiConfig;

/**
 * Builds QR-code SVGs for two-factor authentication setup. `generateQrCodeSvg()` reuses the
 * user's existing {@see User::getAuthTfKey()} secret if present, while `regenerateQrCodeSvg()`
 * always issues a fresh secret. Every method here assumes {@see isAvailable()} has already been
 * checked by the caller.
 */
final readonly class QrCodeUriGeneratorService
{
    public function __construct(
        private VoytiConfig $config,
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
        $twoFactorQrCode = new TwoFactorQRCode(['outputBase64' => false, 'connectPaths' => true, 'scale' => 4]);

        $secret = $this->resolveSecret($user, $secret);
        try {
            $twoFactorQrCode->setSecret($secret);
        } catch (InvalidArgumentException) {
            // auth_tf_key also stores the email 2FA one-time code, so a leftover non-base32
            // value can survive here despite auth_tf_type reading 'google' (e.g. an interrupted
            // method switch) - fall back to a fresh secret rather than surfacing the exception.
            $secret = $this->resolveSecret($user, null);
            $twoFactorQrCode->setSecret($secret);
        }

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
