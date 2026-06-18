<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Rbac;

use Throwable;
use YiiRocks\Voyti\Form\Rbac\AbstractAuthItemForm;
use YiiRocks\Voyti\Validator\Rbac\ItemsValidator;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;

final class ItemEditionService
{
    public function __construct(
        private readonly ManagerInterface $authManager,
        private readonly ItemsStorageInterface $itemsStorage,
        private readonly ItemsValidator $itemsValidator,
    ) {
    }

    public function create(AbstractAuthItemForm $form): bool
    {
        if ($form->getType() === 'role') {
            $item = new Role($form->name);
        } else {
            $item = new Permission($form->name);
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
        } catch (Throwable) {
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
        } catch (Throwable) {
            return false;
        }
    }

    public function update(AbstractAuthItemForm $form): bool
    {
        $oldName = $form->itemName !== '' ? $form->itemName : $form->name;

        if ($form->getType() === 'role') {
            $item = new Role($form->name);
        } else {
            $item = new Permission($form->name);
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
        } catch (Throwable) {
            return false;
        }
    }

    private function updateChildren(string $parentName, array $children): void
    {
        $validationResult = $this->itemsValidator->validate($children);
        if (!$validationResult->isValid()) {
            return;
        }

        foreach ($children as $childName) {
            if ($childName !== '' && !$this->authManager->hasChild($parentName, $childName)) {
                $this->authManager->addChild($parentName, $childName);
            }
        }
    }
}
