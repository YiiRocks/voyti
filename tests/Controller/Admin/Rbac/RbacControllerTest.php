<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\Rbac;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use YiiRocks\Voyti\Controller\Admin\Rbac\RbacController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\tests\Support\SimpleAssignmentsStorage;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
final class RbacControllerTest extends DatabaseTestCase
{
    use TestContainerTrait;

    private AssignmentsStorageInterface $assignmentsStorage;
    private FlashInterface&MockObject $flash;
    private ItemsStorageInterface $itemsStorage;
    private ManagerInterface $manager;
    private ValidatorInterface&MockObject $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->itemsStorage = new SimpleItemsStorage();
        $this->assignmentsStorage = new SimpleAssignmentsStorage();
        $this->manager = new Manager($this->itemsStorage, $this->assignmentsStorage);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
    }

    public static function itemTypeProvider(): array
    {
        return [
            'role' => ['role', 'admin-rbac-roles', 'editor'],
            'permission' => ['permission', 'admin-rbac-permissions', 'edit-posts'],
        ];
    }

    public function testCreate(): void
    {
        $this->itemsStorage->add(new Permission('other-permission'));
        $this->itemsStorage->add(new Role('some-role'));
        $this->itemsStorage->add(new Role('other-role'));
        $this->itemsStorage->add(new Role('child-role'));
        $this->itemsStorage->add(new Role('child2'));

        // GET: Permission shows only permissions, not roles
        $html = (string) $this->createController()
            ->create(request: new ServerRequest('GET', '/'), itemType: 'permission', indexRouteName: 'admin-rbac-permissions')
            ->getBody();
        self::assertStringContainsString('value="other-permission"', $html);
        self::assertStringNotContainsString('value="some-role"', $html);
        self::assertStringContainsString('<h1>Create permission</h1>', $html);
        self::assertStringContainsString('action="//voyti/admin-rbac-permissions-create"', $html);

        // GET: Role shows both roles and permissions
        $html = (string) $this->createController()
            ->create(request: new ServerRequest('GET', '/'), itemType: 'role', indexRouteName: 'admin-rbac-roles')
            ->getBody();
        self::assertStringContainsString('value="other-role"', $html);
        self::assertStringContainsString('value="other-permission"', $html);
        self::assertStringContainsString('<h1>Create role</h1>', $html);
        self::assertStringContainsString('action="//voyti/admin-rbac-roles-create"', $html);

        // POST: Successful create with audit log
        $this->validator->method('validate')->willReturn(new Result());
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'editor', 'description' => '', 'rule' => '', 'children' => ['']],
        ]);
        $result = $this->createController()->create(request: $request, itemType: 'role', indexRouteName: 'admin-rbac-roles');
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//voyti/admin-rbac-roles', $result->getHeaderLine('Location'));
        $this->assertNotNull($this->itemsStorage->getRole('editor'));
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'rbac.role.create'])->all());

        // POST: Clean children assignment
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'parent', 'description' => '', 'rule' => '', 'children' => ['child-role']],
        ]);
        $result = $this->createController()->create(request: $request, itemType: 'role', indexRouteName: 'admin-rbac-roles');
        $this->assertSame(302, $result->getStatusCode());
        $this->assertTrue($this->itemsStorage->hasChild('parent', 'child-role'));

        // POST: Non-strings and gaps are filtered and reindexed
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'parent2', 'description' => '', 'rule' => '', 'children' => [0 => 'child-role', 2 => 123, 4 => 'child2', 6 => false]],
        ]);
        $result = $this->createController()->create(request: $request, itemType: 'role', indexRouteName: 'admin-rbac-roles');
        $this->assertSame(302, $result->getStatusCode());
        $this->assertTrue($this->itemsStorage->hasChild('parent2', 'child-role'));
        $this->assertTrue($this->itemsStorage->hasChild('parent2', 'child2'));

        // POST: Nonexistent child shows error but item persists
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'parent3', 'description' => '', 'rule' => '', 'children' => ['missing-child']],
        ]);
        $html = (string) $this->createController()->create(request: $request, itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();
        self::assertStringContainsString('Child "missing-child" does not exist.', $html);
        $this->assertNotNull($this->itemsStorage->getRole('parent3'));

        // POST: With rule
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'permission' => ['name' => 'perm-with-rule', 'description' => '', 'rule' => 'ownerRule', 'children' => ['']],
        ]);
        $result = $this->createController()->create(request: $request, itemType: 'permission', indexRouteName: 'admin-rbac-permissions');
        $this->assertSame(302, $result->getStatusCode());
        $perm = $this->itemsStorage->getPermission('perm-with-rule');
        $this->assertNotNull($perm);
        $this->assertSame('ownerRule', $perm->getRuleName());
    }

    public function testCreateValidationError(): void
    {
        $validationResult = new Result();
        $validationResult->addError('Name is required.');
        $this->validator->method('validate')->willReturn($validationResult);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => '', 'description' => '', 'rule' => '', 'children' => ['']],
        ]);

        $html = (string) $this->createController()->create(request: $request, itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();

        self::assertStringContainsString('Name is required.', $html);
        self::assertStringContainsString('<h1>Create role</h1>', $html);
    }

    #[DataProvider('itemTypeProvider')]
    public function testDeleteRemovesItem(string $itemType, string $indexRouteName, string $itemName): void
    {
        $this->addItem($itemType, $itemName);

        $result = $this->createController()->delete(new ServerRequest('POST', '/'), $itemName, $itemType, $indexRouteName);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//voyti/' . $indexRouteName, $result->getHeaderLine('Location'));
        $this->assertNull($this->getItem($itemType, $itemName));
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'rbac.' . $itemType . '.delete'])->all());
    }

    public function testIndex(): void
    {
        $this->itemsStorage->add((new Role('admin'))->withDescription('platform staff'));
        $this->itemsStorage->add((new Role('editor'))->withDescription('content team'));
        $this->itemsStorage->add(new Role('parentrole'));
        $this->itemsStorage->add(new Role('kidrole'));
        $this->manager->addChild('parentrole', 'kidrole');

        $controller = $this->createController();

        // Shows all items with their routes
        $html = (string) $controller->index(itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();
        self::assertStringContainsString('>admin</div>', $html);
        self::assertStringContainsString('>editor</div>', $html);
        self::assertStringContainsString('<h1>Roles</h1>', $html);
        self::assertStringContainsString('>Create role<', $html);
        self::assertStringContainsString('href="//voyti/admin-rbac-roles-create"', $html);
        self::assertStringContainsString('action="//voyti/admin-rbac-roles"', $html);
        self::assertStringContainsString('href="//voyti/admin-rbac-roles-update?name=admin"', $html);
        self::assertStringContainsString('action="//voyti/admin-rbac-roles-delete?name=admin"', $html);

        // Filters by name
        $html = (string) $controller->index(filterName: 'admin', itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();
        self::assertStringContainsString('col-3 text-break">admin</div>', $html);
        self::assertStringNotContainsString('col-3 text-break">editor</div>', $html);

        // Filters by description
        $html = (string) $controller->index(filterDescription: 'platform', itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();
        self::assertStringContainsString('col-3 text-break">admin</div>', $html);
        self::assertStringNotContainsString('col-3 text-break">editor</div>', $html);

        // Shows items with their children
        $html = (string) $controller->index(itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();
        self::assertStringContainsString('>parentrole</div>', $html);
        self::assertStringContainsString('col-2 text-break">kidrole</div>', $html);

        // Lists permissions (getPermissions branch) with their routes
        $this->itemsStorage->add((new Permission('edit-posts'))->withDescription('editing'));
        $html = (string) $controller->index(itemType: 'permission', indexRouteName: 'admin-rbac-permissions')->getBody();
        self::assertStringContainsString('>edit-posts</div>', $html);
        self::assertStringContainsString('<h1>Permissions</h1>', $html);
        self::assertStringContainsString('href="//voyti/admin-rbac-permissions-update?name=edit-posts"', $html);
        self::assertStringContainsString('action="//voyti/admin-rbac-permissions-delete?name=edit-posts"', $html);
    }

    public function testUpdate(): void
    {
        $assignedUser = new User();
        $assignedUser->setUsername('assigned');
        $assignedUser->setEmail('assigned@example.com');
        $assignedUser->setPasswordHash('hash');
        $assignedUser->setAuthKey('key');
        $assignedUser->setCreatedAt(time());
        $assignedUser->setUpdatedAt(time());
        $assignedUser->save();

        $this->itemsStorage->add(new Permission('ownerRule-perm'));
        $this->itemsStorage->add(new Role('childrole'));
        $this->itemsStorage->add((new Role('editor'))->withRuleName('ownerRule'));
        $this->itemsStorage->add(new Role('zzz-role'));
        $this->itemsStorage->add(new Permission('aaa-permission'));
        $this->itemsStorage->add(new Role('parent'));
        $this->itemsStorage->add(new Role('oldchild'));
        $this->itemsStorage->add(new Role('newchild'));
        $this->itemsStorage->add(new Role('oldrole'));
        $this->manager->addChild('editor', 'childrole');
        $this->manager->addChild('parent', 'oldchild');
        $this->assignmentsStorage->add(new Assignment('1', 'editor', time()));
        $this->assignmentsStorage->add(new Assignment((string) $assignedUser->getId(), 'editor', time()));

        $this->validator->method('validate')->willReturn(new Result());
        $controller = $this->createController();

        // GET: Prefills rule and checked children
        $html = (string) $controller->update(request: new ServerRequest('GET', '/'), name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();
        self::assertStringContainsString('value="ownerRule"', $html);
        self::assertMatchesRegularExpression('/name="role\[children\]\[\]" value="childrole"[^>]*checked/', $html);
        self::assertStringContainsString('<h1>Update role: editor</h1>', $html);
        self::assertStringContainsString('action="//voyti/admin-rbac-roles-update?name=editor"', $html);

        // GET: Shows available children sorted, excluding self
        self::assertStringContainsString('value="zzz-role"', $html);
        self::assertStringContainsString('value="aaa-permission"', $html);
        self::assertStringNotContainsString('name="role[children][]" value="editor"', $html);
        self::assertLessThan(
            strpos($html, 'value="zzz-role"'),
            strpos($html, 'value="aaa-permission"'),
        );

        // GET: Shows assigned users
        self::assertStringContainsString('assigned', $html);
        self::assertStringContainsString('name="assignedUsers[]" value="' . $assignedUser->getId() . '"', $html);

        // GET: Error for nonexistent item
        $html = (string) $controller->update(request: new ServerRequest('GET', '/'), name: 'nonexistent', itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();
        self::assertStringContainsString('Authorization item not found', $html);

        // POST: Successful update with description and audit log
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'editor', 'description' => 'Updated', 'rule' => '', 'children' => [''], 'assignedUsers' => []],
        ]);
        $result = $controller->update(request: $request, name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles');
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//voyti/admin-rbac-roles', $result->getHeaderLine('Location'));
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'rbac.role.update'])->all());

        // POST: Assigns and unassigns users (empty-string id ignored)
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'editor', 'description' => 'Updated', 'rule' => '', 'children' => [''], 'assignedUsers' => ['2', '']],
        ]);
        $controller->update(request: $request, name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles');
        $this->assertNull($this->assignmentsStorage->get('editor', '1'));
        $this->assertNotNull($this->assignmentsStorage->get('editor', '2'));
        $this->assertNull($this->assignmentsStorage->get('editor', '0'));

        // POST: Removes replaced children
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'parent', 'description' => '', 'rule' => '', 'children' => ['newchild'], 'assignedUsers' => []],
        ]);
        $controller->update(request: $request, name: 'parent', itemType: 'role', indexRouteName: 'admin-rbac-roles');
        $this->assertTrue($this->itemsStorage->hasChild('parent', 'newchild'));
        $this->assertFalse($this->itemsStorage->hasChild('parent', 'oldchild'));

        // POST: Renames item and records previous name in audit log
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'newrole', 'description' => '', 'rule' => '', 'children' => [], 'assignedUsers' => []],
        ]);
        $controller->update(request: $request, name: 'oldrole', itemType: 'role', indexRouteName: 'admin-rbac-roles');
        $this->assertNotNull($this->itemsStorage->getRole('newrole'));
        $this->assertNull($this->itemsStorage->getRole('oldrole'));
        $logs = UserAuditLog::search(['action' => 'rbac.role.update'])->all();
        self::assertNotEmpty($logs);
        self::assertStringContainsString('oldrole', (string) $logs[0]->getContext());

        // POST: Nonexistent child shows error but item persists
        $this->itemsStorage->add(new Role('testitem'));
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'testitem', 'description' => 'Updated', 'rule' => '', 'children' => ['missing-child']],
        ]);
        $html = (string) $controller->update(request: $request, name: 'testitem', itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();
        self::assertStringContainsString('Child "missing-child" does not exist.', $html);
        $role = $this->itemsStorage->getRole('testitem');
        $this->assertNotNull($role);
        $this->assertSame('Updated', $role->getDescription());

        // POST: With rule
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'permission' => ['name' => 'edit-posts', 'description' => '', 'rule' => 'someRule', 'children' => [''], 'assignedUsers' => []],
        ]);
        $result = $controller->update(request: $request, name: 'ownerRule-perm', itemType: 'permission', indexRouteName: 'admin-rbac-permissions');
        $this->assertSame(302, $result->getStatusCode());
        $item = $this->itemsStorage->getPermission('edit-posts');
        $this->assertNotNull($item);
        $this->assertSame('someRule', $item->getRuleName());
    }

    #[DataProvider('itemTypeProvider')]
    public function testUpdatePostThrowsWhenItemMissingFromItemsStorage(string $itemType, string $indexRouteName, string $itemName): void
    {
        $managerOnlyStorage = new SimpleItemsStorage();
        $item = $itemType === 'role' ? new Role($itemName) : new Permission($itemName);
        $managerOnlyStorage->add($item);
        $manager = new Manager($managerOnlyStorage, $this->assignmentsStorage);

        $this->validator->method('validate')->willReturn(new Result());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(ucfirst($itemType) . " '$itemName' not found.");

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            $itemType => ['name' => $itemName, 'description' => '', 'rule' => '', 'children' => []],
        ]);

        $controller = $this->getTestContainer([
            ItemsStorageInterface::class => $this->itemsStorage,
            ManagerInterface::class => $manager,
            AssignmentsStorageInterface::class => $this->assignmentsStorage,
            ValidatorInterface::class => $this->validator,
            FlashInterface::class => $this->flash,
        ])->get(RbacController::class);
        $controller->update(request: $request, name: $itemName, itemType: $itemType, indexRouteName: $indexRouteName);
    }

    public function testUpdateValidationError(): void
    {
        $this->itemsStorage->add(new Role('testitem'));
        $validationResult = new Result();
        $validationResult->addError('Name is required.');
        $this->validator->method('validate')->willReturn($validationResult);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => '', 'description' => '', 'rule' => '', 'children' => []],
        ]);

        $html = (string) $this->createController()->update(request: $request, name: 'testitem', itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();

        self::assertStringContainsString('Name is required.', $html);
    }

    private function addItem(string $itemType, string $name): void
    {
        $item = $itemType === 'role' ? new Role($name) : new Permission($name);
        $this->itemsStorage->add($item);
    }

    private function createController(): RbacController
    {
        return $this->getTestContainer([
            ItemsStorageInterface::class => $this->itemsStorage,
            ManagerInterface::class => $this->manager,
            AssignmentsStorageInterface::class => $this->assignmentsStorage,
            ValidatorInterface::class => $this->validator,
            FlashInterface::class => $this->flash,
        ])->get(RbacController::class);
    }

    private function getItem(string $itemType, string $name): Role|Permission|null
    {
        return $itemType === 'role'
            ? $this->itemsStorage->getRole($name)
            : $this->itemsStorage->getPermission($name);
    }
}
