<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\Security;

use YiiRocks\Voyti\Model\UserToken;
use Yiisoft\FormModel\FormModel;

final class ResetPasswordEvent
{
    public const string AFTER_RESET = 'afterReset';
    public const string BEFORE_TOKEN_VALIDATE = 'beforeTokenValidate';

    public function __construct(
        private readonly UserToken $userToken,
        private ?FormModel $form = null,
    ) {
    }

    public function getForm(): ?FormModel
    {
        return $this->form;
    }

    public function getToken(): UserToken
    {
        return $this->userToken;
    }

    public function updateForm(FormModel $form): self
    {
        $this->form = $form;
        return $this;
    }
}
