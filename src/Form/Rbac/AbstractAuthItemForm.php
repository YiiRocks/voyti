<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form\Rbac;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

abstract class AbstractAuthItemForm extends FormModel implements RulesProviderInterface
{
    public array $children = [];
    #[Length(max: 191)]
    public string $description = '';
    public string $itemName = '';
    #[Required]
    #[Regex(pattern: '/^\w[\w.:\-]+\w$/u')]
    #[Length(min: 1, max: 126)]
    public string $name = '';
    public ?string $rule = null;

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return string[]
     *
     * @psalm-return array{name: string, description: string, children: string, rule: string}
     */
    public function getAttributeLabels(): array
    {
        return [
            'name' => $this->translator->translate('voyti.view.name_label', category: 'voyti'),
            'description' => $this->translator->translate('voyti.view.description_label', category: 'voyti'),
            'children' => $this->translator->translate('voyti.view.children_header', category: 'voyti'),
            'rule' => $this->translator->translate('voyti.view.rule.class_label', category: 'voyti'),
        ];
    }

    #[\Override]
    public function getFormName(): string
    {
        return 'authItem';
    }

    #[\Override]
    public function getPropertyLabels(): array
    {
        return $this->getAttributeLabels();
    }

    #[\Override]
    public function getRules(): iterable
    {
        return [
            'name' => [new Required(), new Regex(pattern: '/^\w[\w.:\-]+\w$/u'), new Length(min: 1, max: 126)],
            'description' => [new Length(max: 191)],
        ];
    }

    abstract public function getType(): string;
}
