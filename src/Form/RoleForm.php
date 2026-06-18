<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Form;

final class RoleForm extends AbstractAuthItemForm
{
    public function getType(): string
    {
        return 'role';
    }

    public function getFormName(): string
    {
        return 'role';
    }
}
