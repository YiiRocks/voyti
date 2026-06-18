<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form;

use Yiisoft\FormModel\FormModel;

final class AssignmentForm extends FormModel
{
    public array $items = [];
    public int $userId = 0;

    public function getAttributeLabels(): array
    {
        return [
            'items' => 'Items',
        ];
    }

    public function getFormName(): string
    {
        return 'assignment';
    }
}
