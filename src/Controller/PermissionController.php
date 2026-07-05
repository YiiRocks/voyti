<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller;

use YiiRocks\Voyti\Form\Rbac\AbstractAuthItemForm;
use YiiRocks\Voyti\Form\Rbac\PermissionForm;

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
        return 'permissions';
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
