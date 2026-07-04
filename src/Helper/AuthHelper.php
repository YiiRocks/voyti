<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\Item;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\User\CurrentUser;

final readonly class AuthHelper
{
    public function __construct(
        private ManagerInterface $authManager,
        private ItemsStorageInterface $itemsStorage,
        private AssignmentsStorageInterface $assignmentsStorage,
        private ModuleConfig $config,
        private CurrentUser $currentUser,
    ) {
    }

    /**
     * @return list<string>
     */
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

    /**
     * @return (Permission|Role)[]
     *
     * @psalm-return array<string, Permission|Role>
     */
    public function getUnassignedItems(int $userId): array
    {
        $assigned = $this->assignmentsStorage->getByUserId((string) $userId);
        $assignedNames = array_map(fn (Assignment $a) => $a->getItemName(), $assigned);
        $all = $this->itemsStorage->getAll();
        $unassigned = [];
        foreach ($all as $name => $item) {
            if (!in_array($name, $assignedNames, true)) {
                $unassigned[$name] = $item;
            }
        }
        return $unassigned;
    }

    public function hasRole(int $userId, string $role): bool
    {
        $items = $this->authManager->getItemsByUserId($userId);
        return isset($items[$role]);
    }

    public function isAdmin(int|string|null $userId = null): bool
    {
        $userId ??= $this->currentUser->getId();
        if ($userId === null) {
            return false;
        }
        if ($this->config->administratorPermissionName !== null) {
            $items = $this->authManager->getItemsByUserId($userId);
            return isset($items[$this->config->administratorPermissionName]);
        }
        return false;
    }

    /**
     * @return true
     */
    public function removeItem(Item $item): bool
    {
        if ($item instanceof Role) {
            $this->authManager->removeRole($item->getName());
        } else {
            $this->authManager->removePermission($item->getName());
        }
        $this->authManager->removeChildren($item->getName());
        return true;
    }

}
