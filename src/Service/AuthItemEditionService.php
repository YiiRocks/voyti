<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use YiiRocks\Voyti\Form\AbstractAuthItemForm;

final class AuthItemEditionService
{
    public function __construct(
        private readonly ManagerInterface $authManager,
        private readonly ItemsStorageInterface $itemsStorage,
    ) {
    }

    public function create(AbstractAuthItemForm $form): bool
    {
        if ($form->getType() === 'role') {
            $item = new \Yiisoft\Rbac\Role($form->name);
        } else {
            $item = new \Yiisoft\Rbac\Permission($form->name);
        }

        $item = $item->withDescription($form->description);

        if ($form->rule !== null && $form->rule !== '') {
            $item = $item->withRuleName($form->rule);
        }

        try {
            if ($form->getType() === 'role') {
                $this->authManager->addRole($item);
            } else {
                $this->authManager->addPermission($item);
            }

            $this->updateChildren($form->name, $form->children);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function update(AbstractAuthItemForm $form): bool
    {
        $oldName = $form->itemName !== '' ? $form->itemName : $form->name;

        if ($form->getType() === 'role') {
            $item = new \Yiisoft\Rbac\Role($form->name);
        } else {
            $item = new \Yiisoft\Rbac\Permission($form->name);
        }

        $item = $item->withDescription($form->description);

        if ($form->rule !== null && $form->rule !== '') {
            $item = $item->withRuleName($form->rule);
        }

        try {
            if ($form->getType() === 'role') {
                $this->authManager->updateRole($oldName, $item);
            } else {
                $this->authManager->updatePermission($oldName, $item);
            }

            $this->authManager->removeChildren($form->name);
            $this->updateChildren($form->name, $form->children);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function delete(string $name, string $type): bool
    {
        try {
            if ($type === 'role') {
                $this->authManager->removeRole($name);
            } else {
                $this->authManager->removePermission($name);
            }
            $this->authManager->removeChildren($name);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function updateChildren(string $parentName, array $children): void
    {
        foreach ($children as $childName) {
            if ($childName !== '' && !$this->authManager->hasChild($parentName, $childName)) {
                $this->authManager->addChild($parentName, $childName);
            }
        }
    }
}
