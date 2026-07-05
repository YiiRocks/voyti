<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\TwoFactor;

use chillerlan\Authenticator\Authenticator;
use chillerlan\QRCode\QRCode;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Security\Random;

final readonly class QrCodeUriGeneratorService
{
    public function __construct(
        private ModuleConfig $config,
    ) {
    }

    public function generateQrCodeSvg(User $user): string
    {
        $uri = $this->run($user);
        if ($uri === '') {
            return '';
        }

        if (!class_exists(QRCode::class)) {
            return '';
        }

        $options = ['outputBase64' => false];
        /** @infection-ignore-all scale: the default SVG output module ignores this option entirely (module size follows the viewBox, not scale), so any value here is unobservable in the rendered markup; it's kept only in case the output type is ever switched to a raster format. */
        $options['scale'] = 4;
        $qrcode = new QRCode($options);
        return (string) $qrcode->render($uri);
    }

    public function run(User $user): string
    {
        $secret = $user->getAuthTfKey();
        if ($secret === null || $secret === '') {
            $secret = class_exists(Authenticator::class)
                ? (new Authenticator())->createSecret()
                : Random::string(32);
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
