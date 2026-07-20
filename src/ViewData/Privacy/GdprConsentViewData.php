<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Privacy;

use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\Form\Settings\GdprConsentForm;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Data for the `privacy/gdpr-consent` screen.
 */
final readonly class GdprConsentViewData
{
    /**
     * @param bool $isLocked whether consent was already given - the form should render read-only
     *        in that case, since consent isn't meant to be revocable from this screen
     * @param string|null $consentDateDisplay a localized date string, set only when $isLocked
     */
    private function __construct(
        public string $formSubmitUrl,
        public bool $isLocked,
        public ?string $consentDateDisplay,
    ) {}

    public static function create(GdprConsentForm $form, UrlGeneratorInterface $url, string $locale): self
    {
        $isLocked = $form->consent;

        return new self(
            formSubmitUrl: $url->generate('voyti/privacy-gdpr-consent'),
            isLocked: $isLocked,
            consentDateDisplay: $isLocked && $form->consentDate !== null
                ? TimezoneHelper::formatLocalized($form->consentDate, $locale, $form->timezone)
                : null,
        );
    }
}
