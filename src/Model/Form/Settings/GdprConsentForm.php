<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model\Form\Settings;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\LabelsProviderInterface;

/**
 * Backs the GDPR consent page: whether the user consents and, once given, the consent date and
 * timezone it was recorded in.
 */
final class GdprConsentForm extends FormModel implements LabelsProviderInterface
{
    public bool $consent = false;
    public ?int $consentDate = null;
    public ?string $timezone = null;

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

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

    /**
     * @return string[]
     *
     * @psalm-return array{consent: string}
     */
    #[\Override]
    public function getPropertyLabels(): array
    {
        return [
            'consent' => $this->translator->translate('voyti.view.gdpr.consent_label', category: 'voyti'),
        ];
    }

    #[\Override]
    public function getValidationPropertyLabels(): array
    {
        return $this->getPropertyLabels();
    }
}
