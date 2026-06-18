<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;

final class UpdateAuthAssignmentsService
{
    public function __construct(
        private readonly ManagerInterface $authManager,
        private readonly AssignmentsStorageInterface $assignmentsStorage,
    ) {
    }

    public function run(int $userId, array $items): bool
    {
        $assigned = $this->assignmentsStorage->getByUserId((string) $userId);
        $assignedNames = array_map(fn(\Yiisoft\Rbac\Assignment $a) => $a->getItemName(), $assigned);

        $itemsList = is_array($items) ? $items : [];

        foreach (array_diff($assignedNames, $itemsList) as $itemName) {
            $this->authManager->revoke($itemName, $userId);
        }

        foreach (array_diff($itemsList, $assignedNames) as $itemName) {
            $this->authManager->assign($itemName, $userId);
        }

        return true;
    }
}
