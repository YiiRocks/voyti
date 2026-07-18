<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Rbac;

use YiiRocks\Voyti\Validator\Rbac\ItemsValidator;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;

/**
 * Synchronizes a user's RBAC assignments to a given set of item names, validating them via
 * {@see ItemsValidator} then assigning/revoking through {@see ManagerInterface} to match.
 */
final readonly class UpdateAssignmentsService
{
    public function __construct(
        private ManagerInterface $authManager,
        private AssignmentsStorageInterface $assignmentsStorage,
        private ItemsValidator $itemsValidator,
    ) {}

    public function run(int $userId, array $items): bool
    {
        /** @var list<string> $itemsList */
        $itemsList = array_values(array_filter($items, 'is_string'));

        $validationResult = $this->itemsValidator->validate($itemsList);
        if (!$validationResult->isValid()) {
            return false;
        }

        $assigned = $this->assignmentsStorage->getByUserId((string) $userId);
        $assignedNames = array_map(fn(Assignment $a) => $a->getItemName(), $assigned);

        foreach (array_diff($assignedNames, $itemsList) as $itemName) {
            $this->authManager->revoke($itemName, $userId);
        }

        foreach (array_diff($itemsList, $assignedNames) as $itemName) {
            $this->authManager->assign($itemName, $userId);
        }

        return true;
    }
}
