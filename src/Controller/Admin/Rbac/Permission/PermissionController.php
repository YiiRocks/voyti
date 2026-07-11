<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Admin\Rbac\Permission;

use YiiRocks\Voyti\Controller\Admin\Rbac\AbstractAuthItemController;
use YiiRocks\Voyti\Model\Form\Rbac\AbstractAuthItemForm;
use YiiRocks\Voyti\Model\Form\Rbac\PermissionForm;

final readonly class PermissionController extends AbstractAuthItemController
{
    /**
     * @return PermissionForm
     */
    #[\Override]
    protected function createForm(): AbstractAuthItemForm
    {
        return new PermissionForm($this->translator);
    }

    #[\Override]
    protected function getIndexRouteName(): string
    {
        return 'admin-rbac-permissions';
    }

    /**
     * @return string
     *
     * @psalm-return 'permission'
     */
    #[\Override]
    protected function getItemType(): string
    {
        return 'permission';
    }
}
