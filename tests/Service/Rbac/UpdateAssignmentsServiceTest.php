<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Rbac;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Service\Rbac\UpdateAssignmentsService;
use YiiRocks\Voyti\Validator\Rbac\ItemsValidator;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Validator\Result;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class UpdateAssignmentsServiceTest extends TestCase
{
    public function testRunWithInvalidItemsReturnsFalse(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $assignmentsStorage = $this->createMock(AssignmentsStorageInterface::class);
        $itemsValidator = $this->createMock(ItemsValidator::class);
        $result = new Result();
        $result->addError('Item does not exist.');
        $itemsValidator->method('validate')->willReturn($result);

        $service = new UpdateAssignmentsService($authManager, $assignmentsStorage, $itemsValidator);
        self::assertFalse($service->run(1, ['invalid_item']));
    }

    public function testRunWithNoChanges(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects($this->never())->method('revoke');
        $authManager->expects($this->never())->method('assign');

        $existingAssignment = $this->createMock(Assignment::class);
        $existingAssignment->method('getItemName')->willReturn('role_a');

        $assignmentsStorage = $this->createMock(AssignmentsStorageInterface::class);
        $assignmentsStorage->method('getByUserId')->willReturn([$existingAssignment]);

        $itemsValidator = $this->createMock(ItemsValidator::class);
        $result = new Result();
        $itemsValidator->method('validate')->willReturn($result);

        $service = new UpdateAssignmentsService($authManager, $assignmentsStorage, $itemsValidator);
        self::assertTrue($service->run(1, ['role_a']));
    }

    public function testRunWithNonStringItemsFilteredOut(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects($this->once())->method('revoke')->with('old_role', 1);
        $authManager->expects($this->once())->method('assign')->with('valid_role', 1);

        $existingAssignment = $this->createMock(Assignment::class);
        $existingAssignment->method('getItemName')->willReturn('old_role');

        $assignmentsStorage = $this->createMock(AssignmentsStorageInterface::class);
        $assignmentsStorage->method('getByUserId')->willReturn([$existingAssignment]);

        $itemsValidator = $this->createMock(ItemsValidator::class);
        $result = new Result();
        $itemsValidator->method('validate')->willReturn($result);

        $service = new UpdateAssignmentsService($authManager, $assignmentsStorage, $itemsValidator);
        self::assertTrue($service->run(1, ['valid_role', 123, null]));
    }

    public function testRunWithValidItemsAddsAndRemoves(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects($this->once())->method('revoke')->with('old_role', 1);
        $authManager->expects($this->once())->method('assign')->with('new_role', 1);

        $existingAssignment = $this->createMock(Assignment::class);
        $existingAssignment->method('getItemName')->willReturn('old_role');

        $assignmentsStorage = $this->createMock(AssignmentsStorageInterface::class);
        $assignmentsStorage->method('getByUserId')->willReturn([$existingAssignment]);

        $itemsValidator = $this->createMock(ItemsValidator::class);
        $result = new Result();
        $itemsValidator->method('validate')->willReturn($result);

        $service = new UpdateAssignmentsService($authManager, $assignmentsStorage, $itemsValidator);
        self::assertTrue($service->run(1, ['new_role']));
    }
}
