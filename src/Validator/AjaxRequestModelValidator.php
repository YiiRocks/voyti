<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Validator;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;

final class AjaxRequestModelValidator
{
    public function __construct(
        private readonly FormModel $form,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function validate(): Result
    {
        return $this->validator->validate($this->form);
    }
}
