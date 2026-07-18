<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\TwoFactor;

use chillerlan\Authenticator\Authenticator;
use chillerlan\QRCode\QRCode;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;

/**
 * Builds TOTP `otpauth://` URIs and QR-code SVGs for two-factor authentication setup. `run()`/
 * `generateQrCodeSvg()` reuse the user's existing {@see User::getAuthTfKey()} secret if present,
 * while `regenerate()`/`regenerateQrCodeSvg()` always issue a fresh secret. Both fall back to an
 * empty string if the optional `chillerlan/php-authenticator`/`chillerlan/php-qrcode` packages
 * aren't installed ({@see isAvailable()}).
 */
final readonly class QrCodeUriGeneratorService
{
    public function __construct(
        private ModuleConfig $config,
    ) {}

    public function generateQrCodeSvg(User $user): string
    {
        return $this->buildSvg($this->run($user));
    }

    public function isAvailable(): bool
    {
        return class_exists(Authenticator::class);
    }

    public function regenerate(User $user): string
    {
        return $this->buildUri($user, null);
    }

    public function regenerateQrCodeSvg(User $user): string
    {
        return $this->buildSvg($this->regenerate($user));
    }

    public function run(User $user): string
    {
        return $this->buildUri($user, $user->getAuthTfKey());
    }

    private function buildSvg(string $uri): string
    {
        if ($uri === '') {
            // @codeCoverageIgnoreStart
            // Only reachable when chillerlan/php-authenticator is missing; always installed in the test environment.
            return '';
            // @codeCoverageIgnoreEnd
        }

        if (!class_exists(QRCode::class)) {
            // @codeCoverageIgnoreStart
            // Only reachable when chillerlan/php-qrcode is missing; always installed in the test environment.
            return '';
            // @codeCoverageIgnoreEnd
        }

        $options = ['outputBase64' => false, 'connectPaths' => true];
        $options['scale'] = 4;
        $qrcode = new QRCode($options);
        return (string) $qrcode->render($uri);
    }

    private function buildUri(User $user, ?string $secret): string
    {
        if ($secret === null || $secret === '') {
            if (!$this->isAvailable()) {
                // @codeCoverageIgnoreStart
                // Only reachable when chillerlan/php-authenticator is missing; always installed in
                // the test environment.
                return '';
                // @codeCoverageIgnoreEnd
            }

            $secret = (new Authenticator())->createSecret();
            $user->setAuthTfKey($secret);
            $user->save();
        }

        $issuer = $this->config->appName;
        $label = $user->getEmail();
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($label),
            $secret,
            rawurlencode($issuer),
        );
    }
}
