<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Rbac;

use RuntimeException;
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

    public function create(AbstractAuthItemForm $form): void
    {
        if ($form->getType() === 'role') {
            $item = (new Role($form->name))->withDescription($form->description);
            if ($form->rule !== null && $form->rule !== '') {
                $item = $item->withRuleName($form->rule);
            }
            $this->authManager->addRole($item);
        } else {
            $item = (new Permission($form->name))->withDescription($form->description);
            if ($form->rule !== null && $form->rule !== '') {
                $item = $item->withRuleName($form->rule);
            }
            $this->authManager->addPermission($item);
        }

        $this->updateChildren($form->name, $form->children);
    }

    public function delete(string $name, string $type): void
    {
        if ($type === 'role') {
            $this->authManager->removeRole($name);
        } else {
            $this->authManager->removePermission($name);
        }
        $this->authManager->removeChildren($name);
    }

    public function update(AbstractAuthItemForm $form): void
    {
        $oldName = $form->itemName !== '' ? $form->itemName : $form->name;

        if ($form->getType() === 'role') {
            $item = $this->itemsStorage->getRole($oldName);
            if ($item === null) {
                throw new RuntimeException("Role '{$oldName}' not found.");
            }
            $item = $item->withName($form->name)->withDescription($form->description);
            if ($form->rule !== null && $form->rule !== '') {
                $item = $item->withRuleName($form->rule);
            }
            $this->authManager->updateRole($oldName, $item);
        } else {
            $item = $this->itemsStorage->getPermission($oldName);
            if ($item === null) {
                throw new RuntimeException("Permission '{$oldName}' not found.");
            }
            $item = $item->withName($form->name)->withDescription($form->description);
            if ($form->rule !== null && $form->rule !== '') {
                $item = $item->withRuleName($form->rule);
            }
            $this->authManager->updatePermission($oldName, $item);
        }

        $this->authManager->removeChildren($form->name);
        $this->updateChildren($form->name, $form->children);
    }

    private function updateChildren(string $parentName, array $children): void
    {
        $children = array_values(array_filter($children, 'is_string'));
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
