<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model\Form\Rbac;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\LabelsProviderInterface;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

/**
 * Backs the admin create/update page for an RBAC item. Shared by both roles and permissions —
 * `$type` ('role'|'permission') is also used as the form name, matching how
 * {@see \YiiRocks\Voyti\Controller\Admin\Rbac\RbacController} branches on `$itemType`.
 */
final class AuthItemForm extends FormModel implements LabelsProviderInterface, RulesProviderInterface
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
        private TranslatorInterface $translator,
        private string $type,
    ) {}

    #[\Override]
    public function getFormName(): string
    {
        return $this->type;
    }

    /**
     * @return string[]
     *
     * @psalm-return array{name: string, description: string, children: string, rule: string}
     */
    #[\Override]
    public function getPropertyLabels(): array
    {
        return [
            'name' => $this->translator->translate('voyti.view.name_label', category: 'voyti'),
            'description' => $this->translator->translate('voyti.view.description_label', category: 'voyti'),
            'children' => $this->translator->translate('voyti.view.children_header', category: 'voyti'),
            'rule' => $this->translator->translate('voyti.view.rule.class_label', category: 'voyti'),
        ];
    }

    #[\Override]
    public function getRules(): iterable
    {
        return [
            'name' => [new Required(), new Regex(pattern: '/^\w[\w.:\-]+\w$/u'), new Length(min: 1, max: 126)],
            'description' => [new Length(max: 191)],
        ];
    }

    public function getType(): string
    {
        return $this->type;
    }

    #[\Override]
    public function getValidationPropertyLabels(): array
    {
        return $this->getPropertyLabels();
    }
}
