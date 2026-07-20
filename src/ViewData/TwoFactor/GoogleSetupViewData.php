<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\TwoFactor;

use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `two-factor/_google` setup fragment.
 */
final readonly class GoogleSetupViewData
{
    /**
     * @param string $qrCodeUri despite the name, this is pre-rendered raw SVG markup (from
     *        `chillerlan/php-qrcode`), not a URI - echo raw (not `Html::encode()`); empty string
     *        when the optional `chillerlan/php-authenticator`/`chillerlan/php-qrcode` packages
     *        aren't installed
     * @param string|null $secret the manually-enterable TOTP secret for "can't scan the code";
     *        null when the user has no stored secret yet (e.g. `chillerlan/php-authenticator`
     *        wasn't available to generate one)
     * @param string $renewLabel already-translated button text
     * @param string $manualEntryLabel already-translated label text
     */
    private function __construct(
        public string $qrCodeUri,
        public ?string $secret,
        public string $renewLabel,
        public string $manualEntryLabel,
        public string $formSubmitUrl,
    ) {}

    public static function create(string $qrCodeUri, ?string $secret, UrlGeneratorInterface $url, TranslatorInterface $translator): self
    {
        return new self(
            qrCodeUri: $qrCodeUri,
            secret: $secret,
            renewLabel: $translator->translate('voyti.view.two_factor.renew'),
            manualEntryLabel: $translator->translate('voyti.view.two_factor.manual_entry'),
            formSubmitUrl: $url->generate('voyti/two-factor-enable'),
        );
    }
}
