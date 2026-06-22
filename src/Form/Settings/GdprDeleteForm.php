<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Settings;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\Required;

final class GdprDeleteForm extends FormModel
{
    #[Required]
    public string $password = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getAttributeLabels(): array
    {
        return [
            'password' => $this->translator->translate('voyti.view.current_password_label', category: 'voyti'),
            'consent' => $this->translator->translate('voyti.view.gdpr.delete_confirm_label', category: 'voyti'),
        ];
    }

    #[\Override]
    public function getPropertyLabel(string $property): string
    {
        $labels = $this->getAttributeLabels();
        if (isset($labels[$property])) {
            return $labels[$property];
        }
        return parent::getPropertyLabel($property);
    }

    #[\Override]
    public function getFormName(): string
    {
        return 'gdpr-delete';
    }
}
