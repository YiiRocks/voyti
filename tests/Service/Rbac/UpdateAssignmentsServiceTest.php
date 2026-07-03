<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service\Rbac;

use YiiRocks\Voyti\Service\Rbac\UpdateAssignmentsService;
use YiiRocks\Voyti\Validator\Rbac\ItemsValidator;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Rbac\SimpleAssignmentsStorage;
use Yiisoft\Rbac\SimpleItemsStorage;

final class UpdateAssignmentsServiceTest extends \PHPUnit\Framework\TestCase
{

    public function testRunAssignsAndRevokesOnlyTheItemsThatDiffer(): void
    {
        $itemsStorage = new InMemoryItemsStorage();
        $itemsStorage->add(new Permission('editor'));
        $itemsStorage->add(new Permission('viewer'));
        $itemsStorage->add(new Permission('admin'));

        $assignmentsStorage = new InMemoryAssignmentsStorage();
        // The user currently has "editor" and "viewer" assigned.
        $assignmentsStorage->add(new Assignment('42', 'editor', 1000));
        $assignmentsStorage->add(new Assignment('42', 'viewer', 1000));

        $manager = new Manager($itemsStorage, $assignmentsStorage, enableDirectPermissions: true);
        $service = $this->createService($manager, $assignmentsStorage, $itemsStorage);

        // Keep "editor", drop "viewer", add "admin".
        $result = $service->run(42, ['editor', 'admin']);

        self::assertTrue($result);

        $assigned = $assignmentsStorage->getByUserId('42');
        self::assertCount(2, $assigned);
        self::assertArrayHasKey('editor', $assigned);
        self::assertArrayHasKey('admin', $assigned);
        self::assertArrayNotHasKey('viewer', $assigned);
    }

    public function testRunDoesNotReassignItemsThatAreAlreadyAssigned(): void
    {
        $itemsStorage = new InMemoryItemsStorage();
        $itemsStorage->add(new Permission('editor'));
        $itemsStorage->add(new Permission('admin'));

        $assignmentsStorage = new InMemoryAssignmentsStorage();
        // The user already has "editor" assigned.
        $assignmentsStorage->add(new Assignment('42', 'editor', 1000));

        $manager = new SpyManager();
        $service = new UpdateAssignmentsService(
            $manager,
            $assignmentsStorage,
            new ItemsValidator($itemsStorage),
        );

        // Keep "editor" (already assigned), add "admin" (not yet assigned).
        $result = $service->run(42, ['editor', 'admin']);

        self::assertTrue($result);
        self::assertSame([], $manager->revokedItemNames);
        // Only the genuinely new item should be assigned; "editor" was already
        // assigned and must not be passed to assign() again.
        self::assertSame(['admin'], $manager->assignedItemNames);
    }

    public function testRunIgnoresNonStringItemsBeforeValidatingAndAssigning(): void
    {
        $itemsStorage = new InMemoryItemsStorage();
        $itemsStorage->add(new Permission('editor'));

        $assignmentsStorage = new InMemoryAssignmentsStorage();

        $manager = new Manager($itemsStorage, $assignmentsStorage, enableDirectPermissions: true);
        $service = $this->createService($manager, $assignmentsStorage, $itemsStorage);

        // Non-string entries (int, null, array) must be stripped before validation/assignment,
        // otherwise the validator would reject them (since ItemsStorage::exists() expects a string
        // key) or the diff/assign calls would blow up.
        $result = $service->run(9, ['editor', 123, null, ['nested']]);

        self::assertTrue($result);

        $assigned = $assignmentsStorage->getByUserId('9');
        self::assertSame(['editor'], array_keys($assigned));
    }
    public function testRunReturnsFalseAndDoesNotChangeAssignmentsWhenAnItemDoesNotExist(): void
    {
        $itemsStorage = new InMemoryItemsStorage();
        $itemsStorage->add(new Permission('editor'));

        $assignmentsStorage = new InMemoryAssignmentsStorage();
        $assignmentsStorage->add(new Assignment('7', 'editor', 1000));

        $manager = new Manager($itemsStorage, $assignmentsStorage, enableDirectPermissions: true);
        $service = $this->createService($manager, $assignmentsStorage, $itemsStorage);

        $result = $service->run(7, ['editor', 'missing-permission']);

        self::assertFalse($result);
        self::assertArrayHasKey('editor', $assignmentsStorage->getByUserId('7'));
        self::assertArrayNotHasKey('missing-permission', $assignmentsStorage->getByUserId('7'));
    }

    public function testRunWithEmptyItemsRevokesAllExistingAssignments(): void
    {
        $itemsStorage = new InMemoryItemsStorage();
        $itemsStorage->add(new Permission('editor'));
        $itemsStorage->add(new Permission('viewer'));

        $assignmentsStorage = new InMemoryAssignmentsStorage();
        $assignmentsStorage->add(new Assignment('3', 'editor', 1000));
        $assignmentsStorage->add(new Assignment('3', 'viewer', 1000));

        $manager = new Manager($itemsStorage, $assignmentsStorage, enableDirectPermissions: true);
        $service = $this->createService($manager, $assignmentsStorage, $itemsStorage);

        $result = $service->run(3, []);

        self::assertTrue($result);
        self::assertSame([], $assignmentsStorage->getByUserId('3'));
    }

    public function testRunWithNoExistingAssignmentsOnlyAssignsRequestedItems(): void
    {
        $itemsStorage = new InMemoryItemsStorage();
        $itemsStorage->add(new Permission('editor'));
        $itemsStorage->add(new Permission('viewer'));

        $assignmentsStorage = new InMemoryAssignmentsStorage();

        $manager = new Manager($itemsStorage, $assignmentsStorage, enableDirectPermissions: true);
        $service = $this->createService($manager, $assignmentsStorage, $itemsStorage);

        $result = $service->run(5, ['editor', 'viewer']);

        self::assertTrue($result);

        $assigned = $assignmentsStorage->getByUserId('5');
        self::assertCount(2, $assigned);
        self::assertArrayHasKey('editor', $assigned);
        self::assertArrayHasKey('viewer', $assigned);
    }

    private function createService(
        Manager $manager,
        InMemoryAssignmentsStorage $assignmentsStorage,
        InMemoryItemsStorage $itemsStorage,
    ): UpdateAssignmentsService {
        return new UpdateAssignmentsService(
            $manager,
            $assignmentsStorage,
            new ItemsValidator($itemsStorage),
        );
    }
}

final class InMemoryAssignmentsStorage extends SimpleAssignmentsStorage
{
}

final class InMemoryItemsStorage extends SimpleItemsStorage
{
}

/**
 * A minimal spy implementation of ManagerInterface that only records
 * assign()/revoke() calls; every other method is unused by UpdateAssignmentsService
 * and throws if invoked.
 */
final class SpyManager implements ManagerInterface
{
    /** @var string[] */
    public array $assignedItemNames = [];

    /** @var string[] */
    public array $revokedItemNames = [];

    public function addChild(string $parentName, string $childName): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function addPermission(Permission $permission): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function addRole(Role $role): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function assign(string $itemName, int|\Stringable|string $userId, ?int $createdAt = null): self
    {
        $this->assignedItemNames[] = $itemName;

        return $this;
    }

    public function canAddChild(string $parentName, string $childName): bool
    {
        throw new \LogicException('Not implemented.');
    }

    public function getChildRoles(string $roleName): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function getDefaultRoleNames(): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function getDefaultRoles(): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function getGuestRole(): ?Role
    {
        throw new \LogicException('Not implemented.');
    }

    public function getGuestRoleName(): ?string
    {
        throw new \LogicException('Not implemented.');
    }

    public function getItemsByUserId(int|\Stringable|string $userId): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function getPermission(string $name): ?Permission
    {
        throw new \LogicException('Not implemented.');
    }

    public function getPermissionsByRoleName(string $roleName): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function getPermissionsByUserId(int|\Stringable|string $userId): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function getRole(string $name): ?Role
    {
        throw new \LogicException('Not implemented.');
    }

    public function getRolesByUserId(int|\Stringable|string $userId): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function getUserIdsByRoleName(string $roleName): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function hasChild(string $parentName, string $childName): bool
    {
        throw new \LogicException('Not implemented.');
    }

    public function hasChildren(string $parentName): bool
    {
        throw new \LogicException('Not implemented.');
    }

    public function removeChild(string $parentName, string $childName): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function removeChildren(string $parentName): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function removePermission(string $name): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function removeRole(string $name): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function revoke(string $itemName, int|\Stringable|string $userId): self
    {
        $this->revokedItemNames[] = $itemName;

        return $this;
    }

    public function revokeAll(int|\Stringable|string $userId): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function setDefaultRoleNames(array|\Closure $roleNames): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function setGuestRoleName(?string $name): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function updatePermission(string $name, Permission $permission): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function updateRole(string $name, Role $role): self
    {
        throw new \LogicException('Not implemented.');
    }

    public function userHasPermission(int|string|\Stringable|null $userId, string $permissionName, array $parameters = []): bool
    {
        throw new \LogicException('Not implemented.');
    }
}
