<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Security;

use YiiRocks\Voyti\Entity\Token;
use Yiisoft\FormModel\FormModel;

final class ResetPasswordEvent
{
    public const AFTER_RESET = 'afterReset';
    public const BEFORE_TOKEN_VALIDATE = 'beforeTokenValidate';

    public function __construct(
        private readonly Token $token,
        private ?FormModel $form = null,
    ) {
    }

    public function getForm(): ?FormModel
    {
        return $this->form;
    }

    public function getToken(): Token
    {
        return $this->token;
    }

    public function updateForm(FormModel $form): self
    {
        $this->form = $form;
        return $this;
    }
}
