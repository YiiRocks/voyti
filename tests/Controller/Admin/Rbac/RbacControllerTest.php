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

    public function testCreateGetShowsAvailableChildrenByType(): void
    {
        $this->itemsStorage->add(new Permission('other-permission'));
        $this->itemsStorage->add(new Role('some-role'));
        $this->itemsStorage->add(new Role('other-role'));

        // Permission: only permissions, not roles
        $html = (string) $this->createController()
            ->create(request: new ServerRequest('GET', '/'), itemType: 'permission', indexRouteName: 'admin-rbac-permissions')
            ->getBody();
        self::assertStringContainsString('value="other-permission"', $html);
        self::assertStringNotContainsString('value="some-role"', $html);

        // Role: both roles and permissions
        $html = (string) $this->createController()
            ->create(request: new ServerRequest('GET', '/'), itemType: 'role', indexRouteName: 'admin-rbac-roles')
            ->getBody();
        self::assertStringContainsString('value="other-role"', $html);
        self::assertStringContainsString('value="other-permission"', $html);
    }

    #[DataProvider('itemTypeProvider')]
    public function testCreatePost(string $itemType, string $indexRouteName, string $itemName): void
    {
        $this->validator->method('validate')->willReturn(new Result());
        $this->itemsStorage->add(new Role('child-role'));
        $this->itemsStorage->add(new Role('child2'));

        // Successful create
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            $itemType => ['name' => $itemName, 'description' => '', 'rule' => '', 'children' => ['']],
        ]);
        $result = $this->createController()->create(request: $request, itemType: $itemType, indexRouteName: $indexRouteName);
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//voyti/' . $indexRouteName, $result->getHeaderLine('Location'));
        $this->assertNotNull($this->getItem($itemType, $itemName));
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'rbac.' . $itemType . '.create'])->all());
    }

    public function testCreatePostWithChildren(): void
    {
        $this->itemsStorage->add(new Role('child-role'));
        $this->itemsStorage->add(new Role('child2'));
        $this->validator->method('validate')->willReturn(new Result());

        // Clean children
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'parent', 'description' => '', 'rule' => '', 'children' => ['child-role']],
        ]);
        $result = $this->createController()->create(request: $request, itemType: 'role', indexRouteName: 'admin-rbac-roles');
        $this->assertSame(302, $result->getStatusCode());
        $this->assertTrue($this->itemsStorage->hasChild('parent', 'child-role'));

        // Non-strings and gaps are filtered and reindexed
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'parent2', 'description' => '', 'rule' => '', 'children' => [0 => 'child-role', 2 => 123, 4 => 'child2', 6 => false]],
        ]);
        $result = $this->createController()->create(request: $request, itemType: 'role', indexRouteName: 'admin-rbac-roles');
        $this->assertSame(302, $result->getStatusCode());
        $this->assertTrue($this->itemsStorage->hasChild('parent2', 'child-role'));
        $this->assertTrue($this->itemsStorage->hasChild('parent2', 'child2'));

        // Nonexistent child shows error but item persists
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'parent3', 'description' => '', 'rule' => '', 'children' => ['missing-child']],
        ]);
        $html = (string) $this->createController()->create(request: $request, itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();
        self::assertStringContainsString('Child "missing-child" does not exist.', $html);
        $this->assertNotNull($this->itemsStorage->getRole('parent3'));

        // With rule
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'permission' => ['name' => 'perm-with-rule', 'description' => '', 'rule' => 'ownerRule', 'children' => ['']],
        ]);
        $result = $this->createController()->create(request: $request, itemType: 'permission', indexRouteName: 'admin-rbac-permissions');
        $this->assertSame(302, $result->getStatusCode());
        $perm = $this->itemsStorage->getPermission('perm-with-rule');
        $this->assertNotNull($perm);
        $this->assertSame('ownerRule', $perm->getRuleName());
    }

    #[DataProvider('itemTypeProvider')]
    public function testCreatePostWithInvalidData(string $itemType, string $indexRouteName, string $itemName): void
    {
        $validationResult = new Result();
        $validationResult->addError('Name is required.');
        $this->validator->method('validate')->willReturn($validationResult);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            $itemType => ['name' => '', 'description' => '', 'rule' => '', 'children' => ['']],
        ]);

        $html = (string) $this->createController()->create(request: $request, itemType: $itemType, indexRouteName: $indexRouteName)->getBody();

        self::assertStringContainsString('Name is required.', $html);
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

        // Shows all items
        $html = (string) $controller->index(itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();
        self::assertStringContainsString('>admin</div>', $html);
        self::assertStringContainsString('>editor</div>', $html);

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

        // Lists permissions (getPermissions branch)
        $this->itemsStorage->add((new Permission('edit-posts'))->withDescription('editing'));
        $html = (string) $controller->index(itemType: 'permission', indexRouteName: 'admin-rbac-permissions')->getBody();
        self::assertStringContainsString('>edit-posts</div>', $html);
    }

    public function testUpdateGet(): void
    {
        $this->itemsStorage->add(new Permission('ownerRule-perm'));
        $this->itemsStorage->add(new Role('childrole'));
        $this->itemsStorage->add((new Role('editor'))->withRuleName('ownerRule'));
        $this->itemsStorage->add(new Role('zzz-role'));
        $this->itemsStorage->add(new Permission('aaa-permission'));
        $this->manager->addChild('editor', 'childrole');

        $controller = $this->createController();

        // Prefills rule and checked children
        $html = (string) $controller->update(request: new ServerRequest('GET', '/'), name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();
        self::assertStringContainsString('value="ownerRule"', $html);
        self::assertMatchesRegularExpression('/name="role\[children\]\[\]" value="childrole"[^>]*checked/', $html);

        // Shows available children sorted, excluding self
        self::assertStringContainsString('value="zzz-role"', $html);
        self::assertStringContainsString('value="aaa-permission"', $html);
        self::assertStringNotContainsString('name="role[children][]" value="editor"', $html);
        self::assertLessThan(
            strpos($html, 'value="zzz-role"'),
            strpos($html, 'value="aaa-permission"'),
        );

        // Shows error for nonexistent item
        $html = (string) $controller->update(request: new ServerRequest('GET', '/'), name: 'nonexistent', itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();
        self::assertStringContainsString('Authorization item not found', $html);
    }

    public function testUpdatePost(): void
    {
        $this->itemsStorage->add(new Role('editor'));
        $this->itemsStorage->add(new Role('parent'));
        $this->itemsStorage->add(new Role('oldchild'));
        $this->itemsStorage->add(new Role('newchild'));
        $this->itemsStorage->add(new Role('oldrole'));
        $this->manager->addChild('parent', 'oldchild');
        $this->assignmentsStorage->add(new Assignment('1', 'editor', time()));

        $this->validator->method('validate')->willReturn(new Result());
        $controller = $this->createController();

        // Successful update with description and audit log
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'editor', 'description' => 'Updated', 'rule' => '', 'children' => [''], 'assignedUsers' => []],
        ]);
        $result = $controller->update(request: $request, name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles');
        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//voyti/admin-rbac-roles', $result->getHeaderLine('Location'));
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'rbac.role.update'])->all());

        // Assigns and unassigns users (empty-string id ignored)
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'editor', 'description' => 'Updated', 'rule' => '', 'children' => [''], 'assignedUsers' => ['2', '']],
        ]);
        $controller->update(request: $request, name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles');
        $this->assertNull($this->assignmentsStorage->get('editor', '1'));
        $this->assertNotNull($this->assignmentsStorage->get('editor', '2'));
        $this->assertNull($this->assignmentsStorage->get('editor', '0'));

        // Removes replaced children
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'parent', 'description' => '', 'rule' => '', 'children' => ['newchild'], 'assignedUsers' => []],
        ]);
        $controller->update(request: $request, name: 'parent', itemType: 'role', indexRouteName: 'admin-rbac-roles');
        $this->assertTrue($this->itemsStorage->hasChild('parent', 'newchild'));
        $this->assertFalse($this->itemsStorage->hasChild('parent', 'oldchild'));

        // Renames item and records previous name in audit log
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'newrole', 'description' => '', 'rule' => '', 'children' => [], 'assignedUsers' => []],
        ]);
        $controller->update(request: $request, name: 'oldrole', itemType: 'role', indexRouteName: 'admin-rbac-roles');
        $this->assertNotNull($this->itemsStorage->getRole('newrole'));
        $this->assertNull($this->itemsStorage->getRole('oldrole'));
        $logs = UserAuditLog::search(['action' => 'rbac.role.update'])->all();
        self::assertNotEmpty($logs);
        self::assertStringContainsString('oldrole', (string) $logs[0]->getContext());
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

    public function testUpdatePostValidationError(): void
    {
        $this->itemsStorage->add(new Role('editor'));

        $validationResult = new Result();
        $validationResult->addError('Name is required.');
        $this->validator->method('validate')->willReturn($validationResult);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => '', 'description' => '', 'rule' => '', 'children' => []],
        ]);

        $html = (string) $this->createController()->update(request: $request, name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();

        self::assertStringContainsString('Name is required.', $html);
    }

    public function testUpdatePostWithNonexistentChild(): void
    {
        $this->itemsStorage->add(new Role('editor'));
        $this->validator->method('validate')->willReturn(new Result());

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'editor', 'description' => 'Updated', 'rule' => '', 'children' => ['missing-child']],
        ]);

        $html = (string) $this->createController()->update(request: $request, name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();

        self::assertStringContainsString('Child "missing-child" does not exist.', $html);
        $role = $this->itemsStorage->getRole('editor');
        $this->assertNotNull($role);
        $this->assertSame('Updated', $role->getDescription());
    }

    #[DataProvider('itemTypeProvider')]
    public function testUpdatePostWithRule(string $itemType, string $indexRouteName, string $itemName): void
    {
        $this->addItem($itemType, $itemName);
        $this->validator->method('validate')->willReturn(new Result());

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            $itemType => ['name' => $itemName, 'description' => '', 'rule' => 'someRule', 'children' => [''], 'assignedUsers' => []],
        ]);

        $result = $this->createController()->update(request: $request, name: $itemName, itemType: $itemType, indexRouteName: $indexRouteName);

        $this->assertSame(302, $result->getStatusCode());
        $item = $this->getItem($itemType, $itemName);
        $this->assertNotNull($item);
        $this->assertSame('someRule', $item->getRuleName());
    }

    public function testUpdateShowsAssignedUsers(): void
    {
        $assignedUser = new User();
        $assignedUser->setUsername('assigned');
        $assignedUser->setEmail('assigned@example.com');
        $assignedUser->setPasswordHash('hash');
        $assignedUser->setAuthKey('key');
        $assignedUser->setCreatedAt(time());
        $assignedUser->setUpdatedAt(time());
        $assignedUser->save();

        $this->itemsStorage->add(new Role('editor'));
        $this->assignmentsStorage->add(new Assignment((string) $assignedUser->getId(), 'editor', time()));

        $html = (string) $this->createController()
            ->update(request: new ServerRequest('GET', '/'), name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles')
            ->getBody();

        self::assertStringContainsString('assigned', $html);
        self::assertStringContainsString('name="assignedUsers[]" value="' . $assignedUser->getId() . '"', $html);
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
