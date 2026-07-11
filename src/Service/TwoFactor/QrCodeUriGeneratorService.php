<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\TwoFactor;

use chillerlan\Authenticator\Authenticator;
use chillerlan\QRCode\QRCode;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;

final readonly class QrCodeUriGeneratorService
{
    public function __construct(
        private ModuleConfig $config,
    ) {
    }

    public function generateQrCodeSvg(User $user, bool $forceNewSecret = false): string
    {
        $uri = $this->run($user, $forceNewSecret);
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

    public function isAvailable(): bool
    {
        return class_exists(Authenticator::class);
    }

    public function run(User $user, bool $forceNewSecret = false): string
    {
        $secret = $forceNewSecret ? null : $user->getAuthTfKey();
        if ($secret === null || $secret === '') {
            if (!$this->isAvailable()) {
                // @codeCoverageIgnoreStart
                // Only reachable when chillerlan/php-authenticator is missing; always installed in the test environment.
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
