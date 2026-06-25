<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Settings;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;

final class UserProfileForm extends FormModel
{
    public string $bio = '';
    public string $name = '';
    public string $publicEmail = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getAttributeLabels(): array
    {
        return [
            'name' => $this->translator->translate('voyti.view.name_label', category: 'voyti'),
            'bio' => $this->translator->translate('voyti.view.bio_label', category: 'voyti'),
            'publicEmail' => $this->translator->translate('voyti.view.public_email_label', category: 'voyti'),
        ];
    }

    #[\Override]
    public function getPropertyLabel(string $property): string
    {
        /** @var array<string, string> $labels */
        $labels = $this->getAttributeLabels();
        if (isset($labels[$property])) {
            return $labels[$property];
        }
        return parent::getPropertyLabel($property);
    }

    #[\Override]
    public function getFormName(): string
    {
        return 'userProfile';
    }
}
