<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

abstract class AbstractAuthItemForm extends FormModel implements RulesProviderInterface
{
    public string $itemName = '';
    #[Required]
    #[Regex(pattern: '/^\w[\w.:\-]+\w$/u')]
    #[Length(min: 1, max: 64)]
    public string $name = '';
    #[Length(max: 255)]
    public string $description = '';
    public ?string $rule = null;
    public array $children = [];

    public function getRules(): iterable
    {
        return [
            'name' => [new Required(), new Regex(pattern: '/^\w[\w.:\-]+\w$/u'), new Length(min: 1, max: 64)],
            'description' => [new Length(max: 255)],
        ];
    }

    public function getAttributeLabels(): array
    {
        return [
            'name' => 'Name',
            'description' => 'Description',
            'children' => 'Children',
            'rule' => 'Rule',
        ];
    }

    public function getFormName(): string
    {
        return 'authItem';
    }

    abstract public function getType(): string;
}
