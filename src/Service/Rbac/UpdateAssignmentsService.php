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
        /**
         * @infection-ignore-all
         *
         * array_values() only re-indexes to satisfy the list<string> type; validate(),
         * array_diff(), and the foreach loops below never depend on key order.
         */
        $itemsList = array_values(array_filter($items, 'is_string'));

        $validationResult = $this->itemsValidator->validate($itemsList);
        if (!$validationResult->isValid()) {
            return false;
        }

        $assigned = $this->assignmentsStorage->getByUserId((string) $userId);
        $assignedNames = array_map(fn (Assignment $a) => $a->getItemName(), $assigned);

        foreach (array_diff($assignedNames, $itemsList) as $itemName) {
            $this->authManager->revoke($itemName, $userId);
        }

        foreach (array_diff($itemsList, $assignedNames) as $itemName) {
            $this->authManager->assign($itemName, $userId);
        }

        return true;
    }
}
