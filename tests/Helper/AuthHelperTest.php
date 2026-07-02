<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\User\CurrentUser;

final class AuthHelperTest extends TestCase
{
    public function testGetRuleNamesReturnsEmptyArrayWhenNoRulesConfigured(): void
    {
        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $itemsStorage->expects(self::once())->method('getAll')->willReturn(['admin' => new Role('admin')]);
        $authManager = $this->createStub(ManagerInterface::class);
        $helper = $this->createHelper($authManager, new ModuleConfig(), $itemsStorage);

        $ruleNames = $helper->getRuleNames();
        self::assertSame([], $ruleNames);
    }

    public function testGetRuleNamesReturnsUniqueRuleClassNames(): void
    {
        $role1 = (new Role('admin'))->withRuleName('App\\Rule\\AdminRule');
        $role2 = (new Role('user'))->withRuleName('App\\Rule\\AdminRule');
        $perm = (new Permission('read'))->withRuleName('App\\Rule\\ReadRule');
        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $itemsStorage->expects(self::once())->method('getAll')->willReturn([
            'admin' => $role1,
            'user' => $role2,
            'read' => $perm,
        ]);
        $authManager = $this->createStub(ManagerInterface::class);
        $helper = $this->createHelper($authManager, new ModuleConfig(), $itemsStorage);

        $ruleNames = $helper->getRuleNames();
        self::assertCount(2, $ruleNames);
        self::assertContains('App\\Rule\\AdminRule', $ruleNames);
        self::assertContains('App\\Rule\\ReadRule', $ruleNames);
        self::assertSame([0, 1], array_keys($ruleNames));
    }

    public function testGetUnassignedItemsReturnsItemsNotAssignedToUser(): void
    {
        $itemsStorage = $this->createMock(ItemsStorageInterface::class);
        $itemsStorage->expects(self::once())->method('getAll')->willReturn([
            'admin' => new Role('admin'),
            'user' => new Role('user'),
            'editor' => new Role('editor'),
        ]);
        $assignmentsStorage = $this->createMock(AssignmentsStorageInterface::class);
        $assignmentsStorage->expects(self::once())
            ->method('getByUserId')
            ->with('1')
            ->willReturn(['admin' => new Assignment('admin', 'admin', 1000)]);
        $authManager = $this->createStub(ManagerInterface::class);
        $helper = $this->createHelper($authManager, new ModuleConfig(), $itemsStorage, $assignmentsStorage);

        $unassigned = $helper->getUnassignedItems(1);
        self::assertCount(2, $unassigned);
        self::assertArrayHasKey('user', $unassigned);
        self::assertArrayHasKey('editor', $unassigned);
        self::assertArrayNotHasKey('admin', $unassigned);
    }

    public function testHasRolePassesUserIdToManager(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())
            ->method('getItemsByUserId')
            ->with(42)
            ->willReturn([]);

        $helper = $this->createHelper($authManager, new ModuleConfig());

        $helper->hasRole(42, 'editor');
    }

    public function testHasRoleReturnsFalseForEmptyAssignments(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())
            ->method('getItemsByUserId')
            ->willReturn([]);

        $helper = $this->createHelper($authManager, new ModuleConfig());

        self::assertFalse($helper->hasRole(1, 'editor'));
    }

    public function testHasRoleReturnsFalseWhenUserDoesNotHaveRole(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())
            ->method('getItemsByUserId')
            ->willReturn(['editor' => new Role('editor')]);

        $helper = $this->createHelper($authManager, new ModuleConfig());

        self::assertFalse($helper->hasRole(1, 'admin'));
    }

    public function testHasRoleReturnsTrueWhenUserHasRole(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())
            ->method('getItemsByUserId')
            ->willReturn(['editor' => new Role('editor')]);

        $helper = $this->createHelper($authManager, new ModuleConfig());

        self::assertTrue($helper->hasRole(1, 'editor'));
    }

    public function testIsAdminPassesUserIdToManager(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())
            ->method('getItemsByUserId')
            ->with(42)
            ->willReturn([]);

        $config = new ModuleConfig(administratorPermissionName: 'admin');
        $helper = $this->createHelper($authManager, $config);

        $helper->isAdmin(42);
    }

    public function testIsAdminReturnsFalseForEmptyAssignments(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())
            ->method('getItemsByUserId')
            ->willReturn([]);

        $config = new ModuleConfig(administratorPermissionName: 'admin');
        $helper = $this->createHelper($authManager, $config);

        self::assertFalse($helper->isAdmin(1));
    }

    public function testIsAdminReturnsFalseWhenNoAdminPermissionConfigured(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::never())->method('getItemsByUserId');

        $config = new ModuleConfig(administratorPermissionName: null);
        $helper = $this->createHelper($authManager, $config);

        self::assertFalse($helper->isAdmin(1));
    }

    public function testIsAdminReturnsFalseWhenUserDoesNotHaveAdminPermission(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())
            ->method('getItemsByUserId')
            ->willReturn(['user' => new Role('user')]);

        $config = new ModuleConfig(administratorPermissionName: 'admin');
        $helper = $this->createHelper($authManager, $config);

        self::assertFalse($helper->isAdmin(1));
    }

    public function testIsAdminReturnsTrueWhenUserHasAdminPermission(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())
            ->method('getItemsByUserId')
            ->willReturn(['admin' => new Role('admin')]);

        $config = new ModuleConfig(administratorPermissionName: 'admin');
        $helper = $this->createHelper($authManager, $config);

        self::assertTrue($helper->isAdmin(1));
    }

    public function testRemoveItemRemovesPermissionAndItsChildren(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())->method('removePermission')->with('some_permission');
        $authManager->expects(self::once())->method('removeChildren')->with('some_permission');

        $helper = $this->createHelper($authManager, new ModuleConfig());
        $item = new Permission('some_permission');

        self::assertTrue($helper->removeItem($item));
    }

    public function testRemoveItemRemovesRoleAndItsChildren(): void
    {
        $authManager = $this->createMock(ManagerInterface::class);
        $authManager->expects(self::once())->method('removeRole')->with('some_role');
        $authManager->expects(self::once())->method('removeChildren')->with('some_role');

        $helper = $this->createHelper($authManager, new ModuleConfig());
        $item = new Role('some_role');

        self::assertTrue($helper->removeItem($item));
    }

    private function createHelper(
        ManagerInterface $authManager,
        ModuleConfig $config,
        ?ItemsStorageInterface $itemsStorage = null,
        ?AssignmentsStorageInterface $assignmentsStorage = null,
        ?CurrentUser $currentUser = null,
    ): AuthHelper {
        return new AuthHelper(
            $authManager,
            $itemsStorage ?? $this->createStub(ItemsStorageInterface::class),
            $assignmentsStorage ?? $this->createStub(AssignmentsStorageInterface::class),
            $config,
            $currentUser ?? new CurrentUser(
                $this->createStub(IdentityRepositoryInterface::class),
                new class implements EventDispatcherInterface {
                    public function dispatch(object $event): object
                    {
                        return $event;
                    }
                },
            ),
        );
    }
}
