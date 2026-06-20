<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Rbac;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

final class RuleForm extends FormModel implements RulesProviderInterface
{
    #[Required]
    public string $class = '';
    #[Required]
    #[Regex(pattern: '/^\w[\w.:\-]+\w$/')]
    public string $name = '';
    public string $previousName = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getAttributeLabels(): array
    {
        return [
            'name' => $this->translator->translate('voyti.view.name_label', category: 'voyti'),
            'class' => $this->translator->translate('voyti.view.rule.class_label', category: 'voyti'),
        ];
    }

    public function getPropertyLabel(string $property): string
    {
        $labels = $this->getAttributeLabels();
        return $labels[$property] ?? parent::getPropertyLabel($property);
    }

    #[\Override]
    public function getFormName(): string
    {
        return 'rule';
    }

    #[\Override]
    public function getRules(): iterable
    {
        return [
            'name' => [new Required(), new Regex(pattern: '/^\w[\w.:\-]+\w$/')],
            'class' => [new Required()],
        ];
    }
}
