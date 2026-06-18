<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;
use YiiRocks\Voyti\Entity\User;

final class SettingsForm extends FormModel
{
    public string $username = '';
    public string $email = '';

    #[Length(min: 6, max: 72)]
    public string $password = '';

    private ?User $user = null;

    public function getAttributeLabels(): array
    {
        return [
            'username' => 'Username',
            'email' => 'Email',
            'password' => 'New password',
        ];
    }

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
