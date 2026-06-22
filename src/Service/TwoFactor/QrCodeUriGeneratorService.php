<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\TwoFactor;

use chillerlan\Authenticator\Authenticator;
use chillerlan\QRCode\QRCode;
use YiiRocks\Voyti\Entity\User;
use Yiisoft\Security\Random;

final class QrCodeUriGeneratorService
{
    public function generateQrCodeSvg(User $user): string
    {
        $uri = $this->run($user);
        if ($uri === '') {
            return '';
        }

        if (!class_exists(QRCode::class)) {
            return '';
        }

        $qrcode = new QRCode([
            'scale' => 4,
            'outputBase64' => false,
        ]);
        return $qrcode->render($uri);
    }

    public function run(User $user): string
    {
        $secret = $user->getAuthTfKey();
        if ($secret === null || $secret === '') {
            $secret = Random::string(32);
            $user->setAuthTfKey($secret);
            $user->save();
        }

        if (class_exists(Authenticator::class)) {
            $authenticator = new Authenticator();
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
}
