<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event;

use Yiisoft\FormModel\FormModel;

final class FormEvent
{
    public const BEFORE_REQUEST = 'beforeRequest';
    public const AFTER_REQUEST = 'afterRequest';
    public const BEFORE_RESEND = 'beforeResend';
    public const AFTER_RESEND = 'afterResend';
    public const BEFORE_LOGIN = 'beforeLogin';
    public const AFTER_LOGIN = 'afterLogin';
    public const BEFORE_REGISTER = 'beforeRegister';
    public const AFTER_REGISTER = 'afterRegister';
    public const FAILED_LOGIN = 'failedLogin';

    public function __construct(
        private readonly FormModel $form,
    ) {
    }

    public function getForm(): FormModel
    {
        return $this->form;
    }
}
