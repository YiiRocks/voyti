<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model\Form\Settings;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

/**
 * Backs the two-factor authentication code entry page for a given delivery `$method`.
 */
final class TwoFactorCodeForm extends FormModel
{
    #[Required]
    #[Integer]
    #[Length(exactly: 6)]
    public string $code = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
        public readonly string $method,
    ) {}

    /**
     * @return string[]
     *
     * @psalm-return array{code: string}
     */
    public function getAttributeLabels(): array
    {
        return [
            'code' => $this->translator->translate('voyti.view.two_factor.enter_code', category: 'voyti'),
        ];
    }

    /**
     * @return string
     *
     * @psalm-return ''
     */
    #[\Override]
    public function getFormName(): string
    {
        return '';
    }

    #[\Override]
    public function getPropertyLabels(): array
    {
        return $this->getAttributeLabels();
    }
}
