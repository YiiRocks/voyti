<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Settings;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\Required;

final class GdprDeleteForm extends FormModel
{

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
            'consent' => $this->translator->translate('voyti.view.gdpr.delete_confirm_label', category: 'voyti'),
        ];
    }

    /**
     * @return string
     *
     * @psalm-return 'gdpr-delete'
     */
    #[\Override]
    public function getFormName(): string
    {
        return 'gdpr-delete';
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
