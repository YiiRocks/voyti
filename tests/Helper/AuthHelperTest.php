<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\User\CurrentUser;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class AuthHelperTest extends TestCase
{

    /**
     * @return iterable<string, array{array<string, Role>, bool}>
     */
    public static function hasRoleProvider(): iterable
    {
        yield 'role missing' => [[], false];
        yield 'role exists' => [['admin' => new Role('admin'), 'editor' => new Role('editor')], true];
    }

    /**
     * @return iterable<string, array{int, array<string, Permission>, bool}>
     */
    public static function isAdminWithGivenUserIdProvider(): iterable
    {
        yield 'user is admin' => [3, ['admin' => new Permission('admin')], true];
        yield 'user not admin' => [2, [], false];
    }

    public function testGetRuleNamesReturnsEmptyWhenNoItems(): void
    {
        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $itemsStorage->expects(self::once())->method('getAll')->willReturn([]);

        $helper = $this->createHelper(itemsStorage: $itemsStorage);

        self::assertSame([], $helper->getRuleNames());
    }

    public function testGetRuleNamesReturnsUniqueRuleNames(): void
    {
        $role1 = new Role('admin');
        $role1 = $role1->withRuleName('isAdmin');
        $role2 = new Role('editor');
        $role2 = $role2->withRuleName('isEditor');
        $permission = new Permission('write');
        $permission = $permission->withRuleName('canWrite');
        $roleNoRule = new Role('user');

        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $itemsStorage->expects(self::once())->method('getAll')->willReturn([
            'admin' => $role1,
            'editor' => $role2,
            'write' => $permission,
            'user' => $roleNoRule,
        ]);

        $helper = $this->createHelper(itemsStorage: $itemsStorage);
        $rules = $helper->getRuleNames();

        sort($rules);
        self::assertSame(['canWrite', 'isAdmin', 'isEditor'], $rules);
    }

    public function testGetRuleNamesReturnsUniqueValues(): void
    {
        $role1 = (new Role('admin'))->withRuleName('isAdmin');
        $role2 = (new Role('superadmin'))->withRuleName('isAdmin');

        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $itemsStorage->expects(self::once())->method('getAll')->willReturn([
            'admin' => $role1,
            'superadmin' => $role2,
        ]);

        $helper = $this->createHelper(itemsStorage: $itemsStorage);
        $rules = $helper->getRuleNames();

        self::assertCount(1, $rules);
        self::assertSame(['isAdmin'], $rules);
    }

    public function testGetUnassignedItemsFiltersAssigned(): void
    {
        $items = [
            'read' => new Permission('read'),
            'write' => new Permission('write'),
            'admin' => new Role('admin'),
        ];

        $assigned = [new Assignment('1', 'read', 1234567890)];

        $assignmentsStorage = $this->createMock(AssignmentsStorageInterface::class);
        $assignmentsStorage->expects(self::once())->method('getByUserId')->with('1')->willReturn($assigned);

        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $itemsStorage->expects(self::once())->method('getAll')->willReturn($items);

        $helper = $this->createHelper(
            itemsStorage: $itemsStorage,
            assignmentsStorage: $assignmentsStorage,
        );

        $unassigned = $helper->getUnassignedItems(1);
        self::assertCount(2, $unassigned);
        self::assertArrayHasKey('write', $unassigned);
        self::assertArrayHasKey('admin', $unassigned);
        self::assertArrayNotHasKey('read', $unassigned);
    }

    public function testGetUnassignedItemsReturnsAllWhenNoneAssigned(): void
    {
        $items = [
            'read' => new Permission('read'),
            'write' => new Permission('write'),
        ];

        $assignmentsStorage = $this->createMock(AssignmentsStorageInterface::class);
        $assignmentsStorage->expects(self::once())->method('getByUserId')->with('1')->willReturn([]);

        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $itemsStorage->expects(self::once())->method('getAll')->willReturn($items);

        $helper = $this->createHelper(
            itemsStorage: $itemsStorage,
            assignmentsStorage: $assignmentsStorage,
        );

        self::assertSame($items, $helper->getUnassignedItems(1));
    }

    /**
     * @param array<string, Role> $userItems
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('hasRoleProvider')]
    public function testHasRole(array $userItems, bool $expected): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())->method('getItemsByUserId')->with(1)->willReturn($userItems);

        $helper = $this->createHelper(authManager: $authManager);

        self::assertSame($expected, $helper->hasRole(1, 'admin'));
    }

    /**
     * @param array<string, Permission> $userItems
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('isAdminWithGivenUserIdProvider')]
    public function testIsAdminWithGivenUserId(int $userId, array $userItems, bool $expected): void
    {
        $config = new ModuleConfig(administratorPermissionName: 'admin');

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())->method('getItemsByUserId')->with($userId)->willReturn($userItems);

        $helper = $this->createHelper(
            authManager: $authManager,
            config: $config,
        );

        self::assertSame($expected, $helper->isAdmin($userId));
    }

    public function testIsAdminWithNullUserIdAndNoAdminPermission(): void
    {
        $identity = $this->createMock(\Yiisoft\Auth\IdentityInterface::class);
        $identity->expects(self::once())->method('getId')->willReturn('1');
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $currentUser = new CurrentUser($identityRepository, $eventDispatcher);
        $currentUser->overrideIdentity($identity);

        $config = new ModuleConfig(administratorPermissionName: null);

        $helper = $this->createHelper(config: $config, currentUser: $currentUser);

        self::assertFalse($helper->isAdmin());
    }

    public function testIsAdminWithNullUserIdAndUserIsAdmin(): void
    {
        $identity = $this->createMock(\Yiisoft\Auth\IdentityInterface::class);
        $identity->expects(self::once())->method('getId')->willReturn('1');
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $currentUser = new CurrentUser($identityRepository, $eventDispatcher);
        $currentUser->overrideIdentity($identity);

        $config = new ModuleConfig(administratorPermissionName: 'admin');

        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())->method('getItemsByUserId')->with(1)->willReturn([
            'admin' => new Permission('admin'),
        ]);

        $helper = $this->createHelper(
            authManager: $authManager,
            config: $config,
            currentUser: $currentUser,
        );

        self::assertTrue($helper->isAdmin());
    }

    public function testIsAdminWithNullUserIdReturnsFalseWhenNoCurrentUser(): void
    {
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $currentUser = new CurrentUser($identityRepository, $eventDispatcher);

        $helper = $this->createHelper(currentUser: $currentUser);

        self::assertFalse($helper->isAdmin());
    }
    private function createHelper(
        ?ManagerInterface $authManager = null,
        ?ItemsStorageInterface $itemsStorage = null,
        ?AssignmentsStorageInterface $assignmentsStorage = null,
        ?ModuleConfig $config = null,
        ?CurrentUser $currentUser = null,
    ): AuthHelper {
        $currentUser ??= new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createMock(EventDispatcherInterface::class),
        );
        return new AuthHelper(
            $authManager ?? $this->createMock(ManagerInterface::class),
            $itemsStorage ?? $this->createMock(ItemsStorageInterface::class),
            $assignmentsStorage ?? $this->createMock(AssignmentsStorageInterface::class),
            $config ?? new ModuleConfig(),
            $currentUser,
        );
    }
}
