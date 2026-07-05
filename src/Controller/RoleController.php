<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use YiiRocks\Voyti\Form\Rbac\AbstractAuthItemForm;
use YiiRocks\Voyti\Form\Rbac\RoleForm;

final readonly class RoleController extends AbstractAuthItemController
{
    /**
     * @return RoleForm
     */
    #[\Override]
    protected function createForm(): AbstractAuthItemForm
    {
        return new RoleForm($this->translator);
    }

    #[\Override]
    protected function getIndexRouteName(): string
    {
        return 'roles';
    }

    /**
     * @return string
     *
     * @psalm-return 'role'
     */
    #[\Override]
    protected function getItemType(): string
    {
        return 'role';
    }
}
