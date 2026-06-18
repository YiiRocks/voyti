<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\Item;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;

final class AuthHelperTest extends TestCase
{
    private function createHelper(
        ManagerInterface $authManager,
        ModuleConfig $config,
        ?ItemsStorageInterface $itemsStorage = null,
        ?AssignmentsStorageInterface $assignmentsStorage = null,
    ): AuthHelper {
        return new AuthHelper(
            $authManager,
            $itemsStorage ?? new AuthHelperItemsStorageDouble(),
            $assignmentsStorage ?? new AuthHelperAssignmentsStorageDouble(),
            $config,
        );
    }

    public function testIsAdminReturnsTrueWhenUserHasAdminPermission(): void
    {
        $authManager = new class(['admin' => new Role('admin')]) extends AuthHelperManagerDouble {};
        $config = new ModuleConfig(administratorPermissionName: 'admin');
        $helper = $this->createHelper($authManager, $config);

        self::assertTrue($helper->isAdmin(1));
    }

    public function testIsAdminReturnsFalseWhenUserDoesNotHaveAdminPermission(): void
    {
        $authManager = new class(['user' => new Role('user')]) extends AuthHelperManagerDouble {};
        $config = new ModuleConfig(administratorPermissionName: 'admin');
        $helper = $this->createHelper($authManager, $config);

        self::assertFalse($helper->isAdmin(1));
    }

    public function testIsAdminReturnsFalseWhenNoAdminPermissionConfigured(): void
    {
        $authManager = new AuthHelperManagerDouble([]);
        $helper = $this->createHelper($authManager, new ModuleConfig());

        self::assertFalse($helper->isAdmin(1));
    }

    public function testIsAdminReturnsFalseForEmptyAssignments(): void
    {
        $authManager = new class([]) extends AuthHelperManagerDouble {};
        $config = new ModuleConfig(administratorPermissionName: 'admin');
        $helper = $this->createHelper($authManager, $config);

        self::assertFalse($helper->isAdmin(1));
    }

    public function testHasRoleReturnsTrueWhenUserHasRole(): void
    {
        $authManager = new class(['editor' => new Role('editor')]) extends AuthHelperManagerDouble {};
        $helper = $this->createHelper($authManager, new ModuleConfig());

        self::assertTrue($helper->hasRole(1, 'editor'));
    }

    public function testHasRoleReturnsFalseWhenUserDoesNotHaveRole(): void
    {
        $authManager = new class(['editor' => new Role('editor')]) extends AuthHelperManagerDouble {};
        $helper = $this->createHelper($authManager, new ModuleConfig());

        self::assertFalse($helper->hasRole(1, 'admin'));
    }

    public function testHasRoleReturnsFalseForEmptyAssignments(): void
    {
        $authManager = new AuthHelperManagerDouble([]);
        $helper = $this->createHelper($authManager, new ModuleConfig());

        self::assertFalse($helper->hasRole(1, 'editor'));
    }

    public function testRemoveItemRemovesRoleAndItsChildren(): void
    {
        $authManager = new class([]) extends AuthHelperManagerDouble {
            public array $events = [];

            public function removeRole(string $name): self
            {
                $this->events[] = ['removeRole', $name];
                return $this;
            }

            public function removeChildren(string $parentName): self
            {
                $this->events[] = ['removeChildren', $parentName];
                return $this;
            }
        };

        $helper = $this->createHelper($authManager, new ModuleConfig());
        $item = new Role('some_role');

        self::assertTrue($helper->removeItem($item));
        self::assertCount(2, $authManager->events);
        self::assertSame('removeRole', $authManager->events[0][0]);
        self::assertSame('some_role', $authManager->events[0][1]);
        self::assertSame('removeChildren', $authManager->events[1][0]);
        self::assertSame('some_role', $authManager->events[1][1]);
    }

    public function testRemoveItemRemovesPermissionAndItsChildren(): void
    {
        $authManager = new class([]) extends AuthHelperManagerDouble {
            public array $events = [];

            public function removePermission(string $name): self
            {
                $this->events[] = ['removePermission', $name];
                return $this;
            }

            public function removeChildren(string $parentName): self
            {
                $this->events[] = ['removeChildren', $parentName];
                return $this;
            }
        };

        $helper = $this->createHelper($authManager, new ModuleConfig());
        $item = new Permission('some_permission');

        self::assertTrue($helper->removeItem($item));
        self::assertCount(2, $authManager->events);
        self::assertSame('removePermission', $authManager->events[0][0]);
        self::assertSame('some_permission', $authManager->events[0][1]);
    }

    public function testIsAdminPassesUserIdToManager(): void
    {
        $lastUserId = null;
        $authManager = new class([], $lastUserId) extends AuthHelperManagerDouble {
            public function __construct(array $items = [], private mixed &$lastUserId = null)
            {
                parent::__construct($items);
            }

            public function getItemsByUserId(int|\Stringable|string $userId): array
            {
                $this->lastUserId = $userId;
                return $this->items;
            }
        };

        $config = new ModuleConfig(administratorPermissionName: 'admin');
        $helper = $this->createHelper($authManager, $config);

        $helper->isAdmin(42);
        self::assertSame(42, $lastUserId);
    }

    public function testHasRolePassesUserIdToManager(): void
    {
        $lastUserId = null;
        $authManager = new class([], $lastUserId) extends AuthHelperManagerDouble {
            public function __construct(array $items = [], private mixed &$lastUserId = null)
            {
                parent::__construct($items);
            }

            public function getItemsByUserId(int|\Stringable|string $userId): array
            {
                $this->lastUserId = $userId;
                return $this->items;
            }
        };

        $helper = $this->createHelper($authManager, new ModuleConfig());
        $helper->hasRole(42, 'editor');

        self::assertSame(42, $lastUserId);
    }

    public function testGetRolesReturnsAllRolesFromStorage(): void
    {
        $itemsStorage = new AuthHelperItemsStorageDouble([
            'admin' => new Role('admin'),
            'user' => new Role('user'),
        ]);
        $authManager = new AuthHelperManagerDouble([]);
        $helper = $this->createHelper($authManager, new ModuleConfig(), $itemsStorage);

        $roles = $helper->getRoles();
        self::assertCount(2, $roles);
        self::assertArrayHasKey('admin', $roles);
        self::assertArrayHasKey('user', $roles);
    }

    public function testGetPermissionsReturnsAllPermissionsFromStorage(): void
    {
        $itemsStorage = new AuthHelperItemsStorageDouble([
            'read' => new Permission('read'),
            'write' => new Permission('write'),
        ]);
        $authManager = new AuthHelperManagerDouble([]);
        $helper = $this->createHelper($authManager, new ModuleConfig(), $itemsStorage);

        $permissions = $helper->getPermissions();
        self::assertCount(2, $permissions);
        self::assertArrayHasKey('read', $permissions);
        self::assertArrayHasKey('write', $permissions);
    }

    public function testGetAssignmentsReturnsAssignmentsFromStorage(): void
    {
        $assignmentsStorage = new AuthHelperAssignmentsStorageDouble([
            '1' => [new Assignment('1', 'admin', 1000)],
        ]);
        $authManager = new AuthHelperManagerDouble([]);
        $helper = $this->createHelper($authManager, new ModuleConfig(), null, $assignmentsStorage);

        $assignments = $helper->getAssignments(1);
        self::assertCount(1, $assignments);
        self::assertSame('admin', $assignments[0]->getItemName());
    }

    public function testGetUnassignedItemsReturnsItemsNotAssignedToUser(): void
    {
        $itemsStorage = new AuthHelperItemsStorageDouble([
            'admin' => new Role('admin'),
            'user' => new Role('user'),
            'editor' => new Role('editor'),
        ]);
        $assignmentsStorage = new AuthHelperAssignmentsStorageDouble([
            '1' => [new Assignment('1', 'admin', 1000)],
        ]);
        $authManager = new AuthHelperManagerDouble([]);
        $helper = $this->createHelper($authManager, new ModuleConfig(), $itemsStorage, $assignmentsStorage);

        $unassigned = $helper->getUnassignedItems(1);
        self::assertCount(2, $unassigned);
        self::assertArrayHasKey('user', $unassigned);
        self::assertArrayHasKey('editor', $unassigned);
        self::assertArrayNotHasKey('admin', $unassigned);
    }

    public function testGetRuleNamesReturnsUniqueRuleClassNames(): void
    {
        $role1 = (new Role('admin'))->withRuleName('App\\Rule\\AdminRule');
        $role2 = (new Role('user'))->withRuleName('App\\Rule\\AdminRule');
        $perm = (new Permission('read'))->withRuleName('App\\Rule\\ReadRule');
        $itemsStorage = new AuthHelperItemsStorageDouble([
            'admin' => $role1,
            'user' => $role2,
            'read' => $perm,
        ]);
        $authManager = new AuthHelperManagerDouble([]);
        $helper = $this->createHelper($authManager, new ModuleConfig(), $itemsStorage);

        $ruleNames = $helper->getRuleNames();
        self::assertCount(2, $ruleNames);
        self::assertContains('App\\Rule\\AdminRule', $ruleNames);
        self::assertContains('App\\Rule\\ReadRule', $ruleNames);
        self::assertSame([0, 1], array_keys($ruleNames));
    }

    public function testGetRuleNamesReturnsEmptyArrayWhenNoRulesConfigured(): void
    {
        $itemsStorage = new AuthHelperItemsStorageDouble([
            'admin' => new Role('admin'),
        ]);
        $authManager = new AuthHelperManagerDouble([]);
        $helper = $this->createHelper($authManager, new ModuleConfig(), $itemsStorage);

        $ruleNames = $helper->getRuleNames();
        self::assertSame([], $ruleNames);
    }
}

class AuthHelperManagerDouble implements ManagerInterface
{
    protected array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function getItemsByUserId(int|\Stringable|string $userId): array
    {
        return $this->items;
    }

    public function canAddChild(string $parentName, string $childName): bool
    {
        return true;
    }

    public function addChild(string $parentName, string $childName): self
    {
        return $this;
    }

    public function removeChild(string $parentName, string $childName): self
    {
        return $this;
    }

    public function removeChildren(string $parentName): self
    {
        return $this;
    }

    public function hasChild(string $parentName, string $childName): bool
    {
        return false;
    }

    public function hasChildren(string $parentName): bool
    {
        return false;
    }

    public function assign(string $itemName, int|\Stringable|string $userId, ?int $createdAt = null): self
    {
        return $this;
    }

    public function revoke(string $itemName, int|\Stringable|string $userId): self
    {
        return $this;
    }

    public function revokeAll(int|\Stringable|string $userId): self
    {
        return $this;
    }

    public function getRolesByUserId(int|\Stringable|string $userId): array
    {
        return [];
    }

    public function getChildRoles(string $roleName): array
    {
        return [];
    }

    public function getPermissionsByRoleName(string $roleName): array
    {
        return [];
    }

    public function getPermissionsByUserId(int|\Stringable|string $userId): array
    {
        return [];
    }

    public function getUserIdsByRoleName(string $roleName): array
    {
        return [];
    }

    public function addRole(Role $role): self
    {
        return $this;
    }

    public function getRole(string $name): ?Role
    {
        return null;
    }

    public function updateRole(string $name, Role $role): self
    {
        return $this;
    }

    public function removeRole(string $name): self
    {
        return $this;
    }

    public function addPermission(Permission $permission): self
    {
        return $this;
    }

    public function getPermission(string $name): ?Permission
    {
        return null;
    }

    public function removePermission(string $name): self
    {
        return $this;
    }

    public function updatePermission(string $name, Permission $permission): self
    {
        return $this;
    }

    public function setDefaultRoleNames(array|\Closure $roleNames): self
    {
        return $this;
    }

    public function getDefaultRoleNames(): array
    {
        return [];
    }

    public function getDefaultRoles(): array
    {
        return [];
    }

    public function setGuestRoleName(?string $name): self
    {
        return $this;
    }

    public function getGuestRoleName(): ?string
    {
        return null;
    }

    public function getGuestRole(): ?Role
    {
        return null;
    }

    public function userHasPermission(int|\Stringable|string|null $userId, string $permissionName, array $parameters = []): bool
    {
        return false;
    }
}

class AuthHelperItemsStorageDouble implements ItemsStorageInterface
{
    private array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function getAll(): array
    {
        return $this->items;
    }

    public function getByNames(array $names): array
    {
        return array_intersect_key($this->items, array_flip($names));
    }

    public function get(string $name): Permission|Role|null
    {
        return $this->items[$name] ?? null;
    }

    public function exists(string $name): bool
    {
        return isset($this->items[$name]);
    }

    public function roleExists(string $name): bool
    {
        return isset($this->items[$name]) && $this->items[$name] instanceof Role;
    }

    public function add(Permission|Role $item): void
    {
        $this->items[$item->getName()] = $item;
    }

    public function update(string $name, Permission|Role $item): void
    {
        unset($this->items[$name]);
        $this->items[$item->getName()] = $item;
    }

    public function remove(string $name): void
    {
        unset($this->items[$name]);
    }

    public function getRoles(): array
    {
        return array_filter($this->items, fn(Item $item) => $item instanceof Role);
    }

    public function getRolesByNames(array $names): array
    {
        return array_intersect_key($this->getRoles(), array_flip($names));
    }

    public function getRole(string $name): ?Role
    {
        $item = $this->items[$name] ?? null;
        return $item instanceof Role ? $item : null;
    }

    public function clearRoles(): void
    {
        $this->items = array_filter($this->items, fn(Item $item) => !$item instanceof Role);
    }

    public function getPermissions(): array
    {
        return array_filter($this->items, fn(Item $item) => $item instanceof Permission);
    }

    public function getPermissionsByNames(array $names): array
    {
        return array_intersect_key($this->getPermissions(), array_flip($names));
    }

    public function getPermission(string $name): ?Permission
    {
        $item = $this->items[$name] ?? null;
        return $item instanceof Permission ? $item : null;
    }

    public function clearPermissions(): void
    {
        $this->items = array_filter($this->items, fn(Item $item) => !$item instanceof Permission);
    }

    public function getParents(string $name): array
    {
        return [];
    }

    public function getHierarchy(string $name): array
    {
        return [];
    }

    public function getDirectChildren(string $name): array
    {
        return [];
    }

    public function getAllChildren(string|array $names): array
    {
        return [];
    }

    public function getAllChildRoles(string|array $names): array
    {
        return [];
    }

    public function getAllChildPermissions(string|array $names): array
    {
        return [];
    }

    public function hasChildren(string $name): bool
    {
        return false;
    }

    public function hasChild(string $parentName, string $childName): bool
    {
        return false;
    }

    public function hasDirectChild(string $parentName, string $childName): bool
    {
        return false;
    }

    public function addChild(string $parentName, string $childName): void
    {
    }

    public function removeChild(string $parentName, string $childName): void
    {
    }

    public function removeChildren(string $parentName): void
    {
    }

    public function clear(): void
    {
        $this->items = [];
    }
}

class AuthHelperAssignmentsStorageDouble implements AssignmentsStorageInterface
{
    private array $assignments;

    public function __construct(array $assignments = [])
    {
        $this->assignments = $assignments;
    }

    public function getAll(): array
    {
        return array_merge(...array_values($this->assignments));
    }

    public function getByUserId(string $userId): array
    {
        return $this->assignments[$userId] ?? [];
    }

    public function getByItemNames(array $itemNames): array
    {
        return [];
    }

    public function get(string $itemName, string $userId): ?Assignment
    {
        return null;
    }

    public function exists(string $itemName, string $userId): bool
    {
        return false;
    }

    public function userHasItem(string $userId, array $itemNames): bool
    {
        return false;
    }

    public function filterUserItemNames(string $userId, array $itemNames): array
    {
        return [];
    }

    public function add(Assignment $assignment): void
    {
    }

    public function hasItem(string $name): bool
    {
        return false;
    }

    public function renameItem(string $oldName, string $newName): void
    {
    }

    public function remove(string $itemName, string $userId): void
    {
    }

    public function removeByUserId(string $userId): void
    {
    }

    public function removeByItemName(string $itemName): void
    {
    }

    public function clear(): void
    {
        $this->assignments = [];
    }
}
