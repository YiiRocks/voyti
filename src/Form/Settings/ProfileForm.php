<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Settings;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;

final class ProfileForm extends FormModel
{
    public string $bio = '';
    public string $name = '';
    public string $publicEmail = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getAttributeLabels(): array
    {
        return [
            'name' => $this->translator->translate('voyti.view.name_label', category: 'voyti'),
            'bio' => $this->translator->translate('voyti.view.bio_label', category: 'voyti'),
            'publicEmail' => $this->translator->translate('voyti.view.public_email_label', category: 'voyti'),
        ];
    }

    public function getPropertyLabel(string $property): string
    {
        $labels = $this->getAttributeLabels();
        return $labels[$property] ?? parent::getPropertyLabel($property);
    }

    #[\Override]
    public function getFormName(): string
    {
        return 'profile';
    }
}
