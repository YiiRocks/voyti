<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form;

final class PermissionForm extends AbstractAuthItemForm
{
    public function getType(): string
    {
        return 'permission';
    }

    public function getFormName(): string
    {
        return 'permission';
    }
}
