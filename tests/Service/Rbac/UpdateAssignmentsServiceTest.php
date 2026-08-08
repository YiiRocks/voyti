<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Rbac;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Service\Rbac\UpdateAssignmentsService;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use YiiRocks\Voyti\Validator\Rbac\ItemsValidator;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Role;

#[AllowMockObjectsWithoutExpectations]
final class UpdateAssignmentsServiceTest extends TestCase
{
    public function testRunWithInvalidItemsReturnsFalse(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $assignmentsStorage = $this->createMock(AssignmentsStorageInterface::class);
        $itemsValidator = $this->createItemsValidator();

        $service = new UpdateAssignmentsService($authManager, $assignmentsStorage, $itemsValidator);
        self::assertFalse($service->run(1, ['invalid_item']));
    }

    public function testRunWithNoChanges(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects($this->never())->method('revoke');
        $authManager->expects($this->never())->method('assign');

        $existingAssignment = new Assignment('1', 'role_a', time());

        $assignmentsStorage = $this->createMock(AssignmentsStorageInterface::class);
        $assignmentsStorage->method('getByUserId')->willReturn([$existingAssignment]);

        $itemsValidator = $this->createItemsValidator('role_a');

        $service = new UpdateAssignmentsService($authManager, $assignmentsStorage, $itemsValidator);
        self::assertTrue($service->run(1, ['role_a']));
    }

    public function testRunWithNonStringItemsFilteredOut(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects($this->once())->method('revoke')->with('old_role', 1);
        $authManager->expects($this->once())->method('assign')->with('valid_role', 1);

        $existingAssignment = new Assignment('1', 'old_role', time());

        $assignmentsStorage = $this->createMock(AssignmentsStorageInterface::class);
        $assignmentsStorage->method('getByUserId')->willReturn([$existingAssignment]);

        $itemsValidator = $this->createItemsValidator('valid_role');

        $service = new UpdateAssignmentsService($authManager, $assignmentsStorage, $itemsValidator);
        self::assertTrue($service->run(1, ['valid_role', 123, null]));
    }

    private function createItemsValidator(string ...$roleNames): ItemsValidator
    {
        $storage = new SimpleItemsStorage();
        foreach ($roleNames as $name) {
            $storage->add(new Role($name));
        }

        return new ItemsValidator($storage);
    }
}
