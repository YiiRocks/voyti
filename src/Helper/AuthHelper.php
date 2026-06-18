<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\Item;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use YiiRocks\Voyti\ModuleConfig;

final class AuthHelper
{
    public function __construct(
        private readonly ManagerInterface $authManager,
        private readonly ItemsStorageInterface $itemsStorage,
        private readonly AssignmentsStorageInterface $assignmentsStorage,
        private readonly ModuleConfig $config,
    ) {
    }

    public function isAdmin(int|string $userId): bool
    {
        if ($this->config->administratorPermissionName !== null) {
            $items = $this->authManager->getItemsByUserId($userId);
            return isset($items[$this->config->administratorPermissionName]);
        }
        return false;
    }

    public function hasRole(int $userId, string $role): bool
    {
        $items = $this->authManager->getItemsByUserId($userId);
        return isset($items[$role]);
    }

    public function getRole(string $name): ?\Yiisoft\Rbac\Role
    {
        return $this->authManager->getRole($name);
    }

    public function getPermission(string $name): ?\Yiisoft\Rbac\Permission
    {
        return $this->authManager->getPermission($name);
    }

    public function getRoles(): array
    {
        return $this->itemsStorage->getRoles();
    }

    public function getPermissions(): array
    {
        return $this->itemsStorage->getPermissions();
    }

    public function getAllItems(): array
    {
        return $this->itemsStorage->getAll();
    }

    public function getUnassignedItems(int $userId): array
    {
        $assigned = $this->assignmentsStorage->getByUserId((string) $userId);
        $assignedNames = array_map(fn(Assignment $a) => $a->getItemName(), $assigned);
        $all = $this->itemsStorage->getAll();
        $unassigned = [];
        foreach ($all as $name => $item) {
            if (!in_array($name, $assignedNames, true)) {
                $unassigned[$name] = $item;
            }
        }
        return $unassigned;
    }

    public function getChildren(string $parentName): array
    {
        return $this->itemsStorage->getDirectChildren($parentName);
    }

    public function removeItem(Item $item): bool
    {
        if ($item instanceof \Yiisoft\Rbac\Role) {
            $this->authManager->removeRole($item->getName());
        } else {
            $this->authManager->removePermission($item->getName());
        }
        $this->authManager->removeChildren($item->getName());
        return true;
    }

    public function getAssignments(int $userId): array
    {
        return $this->assignmentsStorage->getByUserId((string) $userId);
    }

    public function assign(string $itemName, int $userId): void
    {
        $this->authManager->assign($itemName, $userId);
    }

    public function revoke(string $itemName, int $userId): void
    {
        $this->authManager->revoke($itemName, $userId);
    }

    public function revokeAll(int $userId): void
    {
        $this->authManager->revokeAll($userId);
    }

    public function getRuleNames(): array
    {
        $rules = [];
        foreach ($this->itemsStorage->getAll() as $item) {
            $ruleName = $item->getRuleName();
            if ($ruleName !== null) {
                $rules[$ruleName] = $ruleName;
            }
        }
        return array_values($rules);
    }
}
