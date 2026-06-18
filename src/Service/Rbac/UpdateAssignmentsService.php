<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Rbac;

use YiiRocks\Voyti\Validator\Rbac\ItemsValidator;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;

final class UpdateAssignmentsService
{
    public function __construct(
        private readonly ManagerInterface $authManager,
        private readonly AssignmentsStorageInterface $assignmentsStorage,
        private readonly ItemsValidator $itemsValidator,
    ) {
    }

    public function run(int $userId, array $items): bool
    {
        $validationResult = $this->itemsValidator->validate($items);
        if (!$validationResult->isValid()) {
            return false;
        }

        $assigned = $this->assignmentsStorage->getByUserId((string) $userId);
        $assignedNames = array_map(fn (Assignment $a) => $a->getItemName(), $assigned);

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
