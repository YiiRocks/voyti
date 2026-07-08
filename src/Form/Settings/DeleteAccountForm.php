<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Settings;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Rule\TrueValue;

final class DeleteAccountForm extends FormModel
{

    #[TrueValue(trueValue: true)]
    public bool $consent = false;
    #[Required]
    public string $password = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
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
            'consent' => $this->translator->translate('voyti.view.delete_account.confirm_label', category: 'voyti'),
        ];
    }

    /**
     * @return string
     *
     * @psalm-return 'delete-account'
     */
    #[\Override]
    public function getFormName(): string
    {
        return 'delete-account';
    }

    #[\Override]
    public function getPropertyLabels(): array
    {
        return $this->getAttributeLabels();
    }
}
