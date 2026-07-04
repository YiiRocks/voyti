<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\User;

use Yiisoft\FormModel\FormModel;

final readonly class FormEvent
{
    public const string AFTER_LOGIN = 'afterLogin';
    public const string AFTER_REGISTER = 'afterRegister';
    public const string AFTER_REQUEST = 'afterRequest';
    public const string AFTER_RESEND = 'afterResend';
    public const string BEFORE_LOGIN = 'beforeLogin';
    public const string BEFORE_REGISTER = 'beforeRegister';
    public const string BEFORE_REQUEST = 'beforeRequest';
    public const string BEFORE_RESEND = 'beforeResend';
    public const string FAILED_LOGIN = 'failedLogin';

    public function __construct(
        private FormModel $form,
    ) {
    }

    public function getForm(): FormModel
    {
        return $this->form;
    }
}
