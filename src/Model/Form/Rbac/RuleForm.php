<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model\Form\Rbac;

use Override;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\LabelsProviderInterface;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

/**
 * Backs the admin create/update page for an RBAC rule (name and rule class).
 */
final class RuleForm extends FormModel implements LabelsProviderInterface, RulesProviderInterface
{
    #[Required]
    public string $class = '';
    #[Required]
    #[Regex(pattern: '/^\w[\w.:\-]+\w$/')]
    public string $name = '';
    public string $previousName = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * @return string
     *
     * @psalm-return 'rule'
     */
    #[Override]
    public function getFormName(): string
    {
        return 'rule';
    }

    /**
     * @return string[]
     *
     * @psalm-return array{name: string, class: string}
     */
    #[Override]
    public function getPropertyLabels(): array
    {
        return [
            'name' => $this->translator->translate('voyti.view.name_label', category: 'voyti'),
            'class' => $this->translator->translate('voyti.view.rule.class_label', category: 'voyti'),
        ];
    }

    #[Override]
    public function getRules(): iterable
    {
        return [
            'name' => [new Required(), new Regex(pattern: '/^\w[\w.:\-]+\w$/')],
            'class' => [new Required()],
        ];
    }

    #[Override]
    public function getValidationPropertyLabels(): array
    {
        return $this->getPropertyLabels();
    }
}
