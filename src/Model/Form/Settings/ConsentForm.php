<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model\Form\Settings;

use Override;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\LabelsProviderInterface;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

/**
 * Backs password-confirmation forms for sensitive actions (account deletion, anonymization).
 */
final class ConsentForm extends FormModel implements LabelsProviderInterface, RulesProviderInterface
{
    #[Required]
    public string $password = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly string $formName,
        private readonly string $formLabelKey,
        private readonly string $formLabelCategory,
    ) {}

    #[Override]
    public function getFormName(): string
    {
        return $this->formName;
    }

    #[Override]
    public function getPropertyLabels(): array
    {
        return [
            'password' => $this->translator->translate($this->formLabelKey, category: $this->formLabelCategory),
        ];
    }

    #[Override]
    public function getRules(): iterable
    {
        return [
            'password' => [new Required()],
        ];
    }

    #[Override]
    public function getValidationPropertyLabels(): array
    {
        return $this->getPropertyLabels();
    }
}
