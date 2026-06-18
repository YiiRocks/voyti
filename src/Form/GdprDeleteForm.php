<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Rule\Required;

final class GdprDeleteForm extends FormModel
{
    #[Required]
    public string $password = '';

    public function getAttributeLabels(): array
    {
        return [
            'password' => 'Current password',
        ];
    }

    public function getFormName(): string
    {
        return 'gdpr-delete';
    }
}
