<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Helper\SecurityHelper;

final class TwoFactorQrCodeUriGeneratorService
{
    public function __construct(
        private readonly SecurityHelper $securityHelper,
    ) {
    }

    public function run(User $user): string
    {
        $secret = $user->getAuthTfKey();
        if ($secret === null || $secret === '') {
            $secret = $this->securityHelper->generateRandomString(32);
            $user->setAuthTfKey($secret);
            $user->save();
        }

        if (class_exists('\chillerlan\Authenticator\Authenticator')) {
            $authenticator = new \chillerlan\Authenticator\Authenticator();
            $authenticator->setSecret($secret);
            return $authenticator->getUri($user->getEmail(), 'Voyti');
        }

        $issuer = 'Voyti';
        $label = $user->getEmail();
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($label),
            $secret,
            rawurlencode($issuer),
        );
    }

    public function generateQrCodeSvg(User $user): string
    {
        $uri = $this->run($user);
        if ($uri === '') {
            return '';
        }

        if (!class_exists('\chillerlan\QRCode\QRCode')) {
            return '';
        }

        $options = new \chillerlan\QRCode\QROptions();
        $options->scale = 4;
        $options->outputBase64 = false;

        $qrcode = new \chillerlan\QRCode\QRCode($options);
        return $qrcode->render($uri);
    }
}
