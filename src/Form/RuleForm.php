<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;

final class RuleForm extends FormModel implements RulesProviderInterface
{
    #[Required]
    #[Regex(pattern: '/^\w[\w.:\-]+\w$/')]
    public string $name = '';
    #[Required]
    public string $class = '';
    public string $previousName = '';

    public function getRules(): iterable
    {
        return [
            'name' => [new Required(), new Regex(pattern: '/^\w[\w.:\-]+\w$/')],
            'class' => [new Required()],
        ];
    }

    public function getAttributeLabels(): array
    {
        return [
            'name' => 'Name',
            'class' => 'Rule class name',
        ];
    }

    public function getFormName(): string
    {
        return 'rule';
    }
}
