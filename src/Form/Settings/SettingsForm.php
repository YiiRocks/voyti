<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Settings;

use YiiRocks\Voyti\Entity\User;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\Length;

final class SettingsForm extends FormModel
{
    public string $email = '';

    #[Length(min: 6, max: 72)]
    public string $password = '';
    public string $username = '';

    private ?User $user = null;

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getAttributeLabels(): array
    {
        return [
            'username' => $this->translator->translate('voyti.view.username_label', category: 'voyti'),
            'email' => $this->translator->translate('voyti.view.email_label', category: 'voyti'),
            'password' => $this->translator->translate('voyti.view.new_password_label', category: 'voyti'),
            'publicEmail' => $this->translator->translate('voyti.view.public_email_label', category: 'voyti'),
            'name' => $this->translator->translate('voyti.view.name_label', category: 'voyti'),
            'bio' => $this->translator->translate('voyti.view.bio_label', category: 'voyti'),
            'currentPassword' => $this->translator->translate('voyti.view.current_password_label', category: 'voyti'),
            'authTfEnabled' => $this->translator->translate('voyti.view.account.two_factor_title', category: 'voyti'),
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
        return 'settings';
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }
}
