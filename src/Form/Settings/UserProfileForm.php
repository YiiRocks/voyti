<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Settings;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;

final class UserProfileForm extends FormModel
{
    public string $bio = '';
    public string $gravatarEmail = '';
    public string $location = '';
    public string $name = '';
    public string $publicEmail = '';
    public string $timezone = '';
    public string $website = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return string[]
     *
     * @psalm-return array{name: string, publicEmail: string, gravatarEmail: string, location: string, website: string, timezone: string, bio: string}
     */
    public function getAttributeLabels(): array
    {
        return [
            'name' => $this->translator->translate('voyti.view.name_label', category: 'voyti'),
            'publicEmail' => $this->translator->translate('voyti.view.public_email_label', category: 'voyti'),
            'gravatarEmail' => $this->translator->translate('voyti.view.gravatar_email_label', category: 'voyti'),
            'location' => $this->translator->translate('voyti.view.location_label', category: 'voyti'),
            'website' => $this->translator->translate('voyti.view.website_label', category: 'voyti'),
            'timezone' => $this->translator->translate('voyti.view.timezone_label', category: 'voyti'),
            'bio' => $this->translator->translate('voyti.view.bio_label', category: 'voyti'),
        ];
    }

    /**
     * @return string
     *
     * @psalm-return 'userProfile'
     */
    #[\Override]
    public function getFormName(): string
    {
        return 'userProfile';
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
}
