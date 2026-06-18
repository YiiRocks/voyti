<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event;

use YiiRocks\Voyti\Entity\Token;

final class ResetPasswordEvent
{
    public const BEFORE_TOKEN_VALIDATE = 'beforeTokenValidate';
    public const AFTER_RESET = 'afterReset';

    public function __construct(
        private readonly Token $token,
        private ?\Yiisoft\FormModel\FormModel $form = null,
    ) {
    }

    public function getToken(): Token
    {
        return $this->token;
    }

    public function getForm(): ?\Yiisoft\FormModel\FormModel
    {
        return $this->form;
    }

    public function updateForm(\Yiisoft\FormModel\FormModel $form): self
    {
        $this->form = $form;
        return $this;
    }
}
