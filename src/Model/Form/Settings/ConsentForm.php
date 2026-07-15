<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model\Form\Settings;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Rule\TrueValue;

final class ConsentForm extends FormModel
{

    #[TrueValue(trueValue: true)]
    public bool $consent = false;
    #[Required]
    public string $password = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly string $formName,
        private readonly string $consentLabel,
    ) {
    }

    /**
     * @return string[]
     *
     * @psalm-return array{password: string, consent: string}
     */
    public function getAttributeLabels(): array
    {
        return [
            'password' => $this->translator->translate('voyti.view.current_password_label', category: 'voyti'),
            'consent' => $this->translator->translate($this->consentLabel, category: 'voyti'),
        ];
    }

    #[\Override]
    public function getFormName(): string
    {
        return $this->formName;
    }

    #[\Override]
    public function getPropertyLabels(): array
    {
        return $this->getAttributeLabels();
    }
}
