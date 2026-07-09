<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Settings;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;

final class GdprConsentForm extends FormModel
{
    public bool $consent = false;
    public ?int $consentDate = null;
    public ?string $timezone = null;

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return string[]
     *
     * @psalm-return array{consent: string}
     */
    public function getAttributeLabels(): array
    {
        return [
            'consent' => $this->translator->translate('voyti.view.gdpr.consent_label', category: 'voyti'),
        ];
    }

    /**
     * @return string
     *
     * @psalm-return 'gdpr-consent'
     */
    #[\Override]
    public function getFormName(): string
    {
        return 'gdpr-consent';
    }

    #[\Override]
    public function getPropertyLabels(): array
    {
        return $this->getAttributeLabels();
    }
}
