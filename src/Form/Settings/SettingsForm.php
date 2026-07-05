<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Settings;

use YiiRocks\Voyti\Entity\User;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\CompareType;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Equal;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

final class SettingsForm extends FormModel
{
    #[Required]
    #[Email(checkDns: true, enableIdn: true, skipOnEmpty: true)]
    #[Length(max: 255)]
    public string $email = '';
    #[Length(min: 6, max: 72)]
    public string $password = '';
    #[Equal(targetProperty: 'password', strict: true, type: CompareType::STRING)]
    public string $passwordRepeat = '';
    #[Required]
    #[Length(min: 3, max: 255)]
    #[Regex(pattern: '/^[-a-zA-Z0-9_\.@]+$/')]
    public string $username = '';

    private ?User $user = null;

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return string[]
     *
     * @psalm-return array{username: string, email: string, password: string, passwordRepeat: string, publicEmail: string, name: string, bio: string, currentPassword: string, authTfEnabled: string}
     */
    public function getAttributeLabels(): array
    {
        return [
            'username' => $this->translator->translate('voyti.view.username_label', category: 'voyti'),
            'email' => $this->translator->translate('voyti.view.email_label', category: 'voyti'),
            'password' => $this->translator->translate('voyti.view.new_password_label', category: 'voyti'),
            'passwordRepeat' => $this->translator->translate('voyti.view.new_password_repeat_label', category: 'voyti'),
            'publicEmail' => $this->translator->translate('voyti.view.public_email_label', category: 'voyti'),
            'name' => $this->translator->translate('voyti.view.name_label', category: 'voyti'),
            'bio' => $this->translator->translate('voyti.view.bio_label', category: 'voyti'),
            'currentPassword' => $this->translator->translate('voyti.view.current_password_label', category: 'voyti'),
            'authTfEnabled' => $this->translator->translate('voyti.view.account.two_factor_title', category: 'voyti'),
        ];
    }

    /**
     * @return string
     *
     * @psalm-return 'settings'
     */
    #[\Override]
    public function getFormName(): string
    {
        return 'settings';
    }

    #[\Override]
    public function getPropertyLabels(): array
    {
        return $this->getAttributeLabels();
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
