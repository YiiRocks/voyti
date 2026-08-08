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

    public function testCreateGetShowsAvailableChildrenExcludingRoles(): void
    {
        $this->itemsStorage->add(new Permission('other-permission'));
        $this->itemsStorage->add(new Role('some-role'));

        $html = (string) $this->createController()
            ->create(request: new ServerRequest('GET', '/'), itemType: 'permission', indexRouteName: 'admin-rbac-permissions')
            ->getBody();

        // A permission may only take permissions (not roles) as children.
        self::assertStringContainsString('value="other-permission"', $html);
        self::assertStringNotContainsString('value="some-role"', $html);
    }

    public function testCreateGetShowsAvailableChildrenIncludingRolesAndPermissions(): void
    {
        $this->itemsStorage->add(new Role('other-role'));
        $this->itemsStorage->add(new Permission('some-permission'));

        $html = (string) $this->createController()
            ->create(request: new ServerRequest('GET', '/'), itemType: 'role', indexRouteName: 'admin-rbac-roles')
            ->getBody();

        // A role may take both roles and permissions as children.
        self::assertStringContainsString('value="other-role"', $html);
        self::assertStringContainsString('value="some-permission"', $html);
    }

    #[DataProvider('itemTypeProvider')]
    public function testCreatePostSuccessful(string $itemType, string $indexRouteName, string $itemName): void
    {
        $this->validator->method('validate')->willReturn(new Result());

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            $itemType => ['name' => $itemName, 'description' => '', 'rule' => '', 'children' => ['']],
        ]);

        $result = $this->createController()->create(request: $request, itemType: $itemType, indexRouteName: $indexRouteName);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//voyti/' . $indexRouteName, $result->getHeaderLine('Location'));
        $this->assertNotNull($this->getItem($itemType, $itemName));
        // The mutating action is recorded in the audit log under the item-type-specific key.
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'rbac.' . $itemType . '.create'])->all());
    }

    public function testCreatePostWithChildren(): void
    {
        $this->itemsStorage->add(new Role('child-role'));

        $this->validator->method('validate')->willReturn(new Result());

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'parent', 'description' => '', 'rule' => '', 'children' => ['child-role']],
        ]);

        $result = $this->createController()->create(request: $request, itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame(302, $result->getStatusCode());
        $this->assertTrue($this->itemsStorage->hasChild('parent', 'child-role'));
    }

    #[DataProvider('itemTypeProvider')]
    public function testCreatePostWithInvalidDataShowsErrors(string $itemType, string $indexRouteName, string $itemName): void
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

    public function testCreatePostWithNonexistentChildShowsErrorsAndPersistsItem(): void
    {
        $this->validator->method('validate')->willReturn(new Result());

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'parent', 'description' => '', 'rule' => '', 'children' => ['missing-child']],
        ]);

        $html = (string) $this->createController()->create(request: $request, itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();

        self::assertStringContainsString('Child "missing-child" does not exist.', $html);
        // The item itself is still persisted; only the missing child is rejected.
        $this->assertNotNull($this->itemsStorage->getRole('parent'));
    }

    public function testCreatePostWithRule(): void
    {
        $this->validator->method('validate')->willReturn(new Result());

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'permission' => ['name' => 'restricted-action', 'description' => '', 'rule' => 'ownerRule', 'children' => ['']],
        ]);

        $result = $this->createController()->create(request: $request, itemType: 'permission', indexRouteName: 'admin-rbac-permissions');

        $this->assertSame(302, $result->getStatusCode());
        $perm = $this->itemsStorage->getPermission('restricted-action');
        $this->assertNotNull($perm);
        $this->assertSame('ownerRule', $perm->getRuleName());
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

    public function testIndexFiltersByDescription(): void
    {
        $this->itemsStorage->add((new Role('admin'))->withDescription('platform staff'));
        $this->itemsStorage->add((new Role('editor'))->withDescription('content team'));

        $html = (string) $this->createController()
            ->index(filterDescription: 'platform', itemType: 'role', indexRouteName: 'admin-rbac-roles')
            ->getBody();

        // The description filter narrows the item rows to the matching role only.
        self::assertStringContainsString('col-3 text-break">admin</div>', $html);
        self::assertStringNotContainsString('col-3 text-break">editor</div>', $html);
    }

    public function testIndexFiltersByName(): void
    {
        $this->itemsStorage->add(new Role('admin'));
        $this->itemsStorage->add(new Role('editor'));

        $html = (string) $this->createController()
            ->index(filterName: 'admin', itemType: 'role', indexRouteName: 'admin-rbac-roles')
            ->getBody();

        // The name filter narrows the item rows to the matching role only.
        self::assertStringContainsString('col-3 text-break">admin</div>', $html);
        self::assertStringNotContainsString('col-3 text-break">editor</div>', $html);
    }

    #[DataProvider('itemTypeProvider')]
    public function testIndexShowsItems(string $itemType, string $indexRouteName, string $itemName): void
    {
        $this->addItem($itemType, $itemName);

        $html = (string) $this->createController()->index(itemType: $itemType, indexRouteName: $indexRouteName)->getBody();

        self::assertStringContainsString('>' . $itemName . '</div>', $html);
    }

    public function testIndexShowsItemsWithTheirChildren(): void
    {
        $this->itemsStorage->add(new Role('parentrole'));
        $this->itemsStorage->add(new Role('kidrole'));
        $this->manager->addChild('parentrole', 'kidrole');

        $html = (string) $this->createController()->index(itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();

        self::assertStringContainsString('>parentrole</div>', $html);
        // The parent's children column lists its child (col-2), distinct from the child's own name column (col-3).
        self::assertStringContainsString('col-2 text-break">kidrole</div>', $html);
    }

    public function testUpdateGetPrefillsRuleAndCheckedChildren(): void
    {
        $this->itemsStorage->add(new Permission('ownerRule-perm'));
        $this->itemsStorage->add(new Role('childrole'));
        $this->itemsStorage->add((new Role('editor'))->withRuleName('ownerRule'));
        $this->manager->addChild('editor', 'childrole');

        $html = (string) $this->createController()
            ->update(request: new ServerRequest('GET', '/'), name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles')
            ->getBody();

        // The rule field is pre-filled and the already-assigned child checkbox is checked.
        self::assertStringContainsString('value="ownerRule"', $html);
        self::assertMatchesRegularExpression('/name="role\[children\]\[\]" value="childrole"[^>]*checked/', $html);
    }

    public function testUpdateGetShowsAvailableChildrenExcludingSelf(): void
    {
        $this->itemsStorage->add(new Role('editor'));
        // Added out of alphabetical order to prove the candidates are sorted before rendering.
        $this->itemsStorage->add(new Role('zzz-role'));
        $this->itemsStorage->add(new Permission('aaa-permission'));

        $html = (string) $this->createController()
            ->update(request: new ServerRequest('GET', '/'), name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles')
            ->getBody();

        self::assertStringContainsString('value="zzz-role"', $html);
        self::assertStringContainsString('value="aaa-permission"', $html);
        // The item can't be its own child (its name still appears in the name field, so match the child input).
        self::assertStringNotContainsString('name="role[children][]" value="editor"', $html);
        // Candidates are sorted by name: aaa-permission renders before zzz-role.
        self::assertLessThan(
            strpos($html, 'value="zzz-role"'),
            strpos($html, 'value="aaa-permission"'),
        );
    }

    #[DataProvider('itemTypeProvider')]
    public function testUpdateNonExistentShowsError(string $itemType, string $indexRouteName, string $itemName): void
    {
        $html = (string) $this->createController()
            ->update(request: new ServerRequest('GET', '/'), name: 'nonexistent', itemType: $itemType, indexRouteName: $indexRouteName)
            ->getBody();

        self::assertStringContainsString('Authorization item not found', $html);
    }

    public function testUpdatePostAssignsAndUnassignsUsers(): void
    {
        $this->itemsStorage->add(new Role('editor'));
        $this->assignmentsStorage->add(new Assignment('1', 'editor', time()));

        $this->validator->method('validate')->willReturn(new Result());

        // The empty-string id must be ignored, not assigned.
        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'editor', 'description' => 'Updated', 'rule' => '', 'children' => [''], 'assignedUsers' => ['2', '']],
        ]);

        $result = $this->createController()->update(request: $request, name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame(302, $result->getStatusCode());
        $this->assertNull($this->assignmentsStorage->get('editor', '1'));
        $this->assertNotNull($this->assignmentsStorage->get('editor', '2'));
        // The empty-string id was filtered out, so no assignment was created for user "0" ((int) '').
        $this->assertNull($this->assignmentsStorage->get('editor', '0'));
    }

    public function testUpdatePostRemovesReplacedChildren(): void
    {
        $this->itemsStorage->add(new Role('parent'));
        $this->itemsStorage->add(new Role('oldchild'));
        $this->itemsStorage->add(new Role('newchild'));
        $this->manager->addChild('parent', 'oldchild');

        $this->validator->method('validate')->willReturn(new Result());

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'parent', 'description' => '', 'rule' => '', 'children' => ['newchild'], 'assignedUsers' => []],
        ]);

        $result = $this->createController()->update(request: $request, name: 'parent', itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame(302, $result->getStatusCode());
        $this->assertTrue($this->itemsStorage->hasChild('parent', 'newchild'));
        // The previously-assigned child is removed before the new set is applied.
        $this->assertFalse($this->itemsStorage->hasChild('parent', 'oldchild'));
    }

    public function testUpdatePostRenamesItemAndRecordsPreviousName(): void
    {
        $this->itemsStorage->add(new Role('oldrole'));

        $this->validator->method('validate')->willReturn(new Result());

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'newrole', 'description' => '', 'rule' => '', 'children' => [], 'assignedUsers' => []],
        ]);

        $result = $this->createController()->update(request: $request, name: 'oldrole', itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame(302, $result->getStatusCode());
        $this->assertNotNull($this->itemsStorage->getRole('newrole'));
        $this->assertNull($this->itemsStorage->getRole('oldrole'));
        // The audit log records the pre-rename name in its context.
        $logs = UserAuditLog::search(['action' => 'rbac.role.update'])->all();
        self::assertNotEmpty($logs);
        self::assertStringContainsString('oldrole', (string) $logs[0]->getContext());
    }

    #[DataProvider('itemTypeProvider')]
    public function testUpdatePostSuccessful(string $itemType, string $indexRouteName, string $itemName): void
    {
        $this->addItem($itemType, $itemName);

        $this->validator->method('validate')->willReturn(new Result());

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            $itemType => ['name' => $itemName, 'description' => 'Updated', 'rule' => '', 'children' => [''], 'assignedUsers' => []],
        ]);

        $result = $this->createController()->update(request: $request, name: $itemName, itemType: $itemType, indexRouteName: $indexRouteName);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('//voyti/' . $indexRouteName, $result->getHeaderLine('Location'));
        $this->assertNotEmpty(UserAuditLog::search(['action' => 'rbac.' . $itemType . '.update'])->all());
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

    public function testUpdatePostWithInvalidDataShowsErrors(): void
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

    public function testUpdatePostWithNonexistentChildShowsErrors(): void
    {
        $this->itemsStorage->add(new Role('editor'));

        $this->validator->method('validate')->willReturn(new Result());

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'editor', 'description' => 'Updated', 'rule' => '', 'children' => ['missing-child']],
        ]);

        $html = (string) $this->createController()->update(request: $request, name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles')->getBody();

        self::assertStringContainsString('Child "missing-child" does not exist.', $html);
        // The item's own fields are still persisted; only the missing child is rejected.
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

        // The assigned user is rendered as a checked assignment with their username.
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
