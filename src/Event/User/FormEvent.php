<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\User;

use Yiisoft\FormModel\FormModel;

final readonly class FormEvent
{
    public function __construct(
        private FormModel $form,
    ) {
    }

    public function getForm(): FormModel
    {
        return $this->form;
    }
}
