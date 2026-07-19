<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model\Form\Settings;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\LabelsProviderInterface;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Rule\TrueValue;

/**
 * Backs a generic password-confirmed consent page (e.g. account deletion); the form name and
 * consent label are injected per use site rather than hardcoded.
 */
final class ConsentForm extends FormModel implements LabelsProviderInterface
{
    #[TrueValue(trueValue: true)]
    public bool $consent = false;
    #[Required]
    public string $password = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly string $formName,
        private readonly string $consentLabel,
    ) {}

    #[\Override]
    public function getFormName(): string
    {
        return $this->formName;
    }

    /**
     * @return string[]
     *
     * @psalm-return array{password: string, consent: string}
     */
    #[\Override]
    public function getPropertyLabels(): array
    {
        return [
            'password' => $this->translator->translate('voyti.view.current_password_label', category: 'voyti'),
            'consent' => $this->translator->translate($this->consentLabel, category: 'voyti'),
        ];
    }

    #[\Override]
    public function getValidationPropertyLabels(): array
    {
        return $this->getPropertyLabels();
    }
}
