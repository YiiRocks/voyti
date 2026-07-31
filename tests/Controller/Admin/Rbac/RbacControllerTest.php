<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\Rbac;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use YiiRocks\Voyti\Controller\Admin\Rbac\RbacController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\Support\SimpleAssignmentsStorage;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use YiiRocks\Voyti\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class RbacControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;
    use TestContainerTrait;

    private AssignmentsStorageInterface $assignmentsStorage;
    private FlashInterface&MockObject $flash;
    private ItemsStorageInterface $itemsStorage;
    private ManagerInterface $manager;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private ValidatorInterface&MockObject $validator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->itemsStorage = new SimpleItemsStorage();
        $this->assignmentsStorage = new SimpleAssignmentsStorage();
        $this->manager = new Manager($this->itemsStorage, $this->assignmentsStorage);
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->viewRenderer->method('withAddedInjections')->willReturnSelf();
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
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

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/create', $this->callback(
                static fn(array $params): bool => array_map(static fn($c) => $c->name, $params['data']->children) === ['other-permission'],
            ))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->create(request: new ServerRequest('GET', '/'), itemType: 'permission', indexRouteName: 'admin-rbac-permissions');

        $this->assertSame($response, $result);
    }

    public function testCreateGetShowsAvailableChildrenIncludingRolesAndPermissions(): void
    {
        $this->itemsStorage->add(new Role('other-role'));
        $this->itemsStorage->add(new Permission('some-permission'));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/create', $this->callback(
                static fn(array $params): bool => array_map(static fn($c) => $c->name, $params['data']->children) === ['other-role', 'some-permission'],
            ))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->create(request: new ServerRequest('GET', '/'), itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame($response, $result);
    }

    #[DataProvider('itemTypeProvider')]
    public function testCreateGetShowsForm(string $itemType, string $indexRouteName, string $itemName): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/create', $this->callback(
                static fn(array $params): bool => $params['data']->errors === [],
            ))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->create(request: new ServerRequest('GET', '/'), itemType: $itemType, indexRouteName: $indexRouteName);

        $this->assertSame($response, $result);
    }

    #[DataProvider('itemTypeProvider')]
    public function testCreatePostSuccessful(string $itemType, string $indexRouteName, string $itemName): void
    {
        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->mockRedirectResponse($this->responseFactory);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            $itemType => ['name' => $itemName, 'description' => '', 'rule' => '', 'children' => ['']],
        ]);

        $controller = $this->createController();
        $result = $controller->create(request: $request, itemType: $itemType, indexRouteName: $indexRouteName);

        $this->assertSame($response, $result);
        $this->assertNotNull($this->getItem($itemType, $itemName));
    }

    public function testCreatePostWithChildren(): void
    {
        $this->itemsStorage->add(new Role('child-role'));

        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->mockRedirectResponse($this->responseFactory);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'parent', 'description' => '', 'rule' => '', 'children' => ['child-role']],
        ]);

        $controller = $this->createController();
        $result = $controller->create(request: $request, itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame($response, $result);
        $this->assertTrue($this->itemsStorage->hasChild('parent', 'child-role'));
    }

    #[DataProvider('itemTypeProvider')]
    public function testCreatePostWithInvalidDataShowsErrors(string $itemType, string $indexRouteName, string $itemName): void
    {
        $validationResult = new Result();
        $validationResult->addError('Name is required.');
        $this->validator->method('validate')->willReturn($validationResult);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/create', $this->callback(
                static fn(array $params): bool => $params['data']->errors !== [],
            ))
            ->willReturn($response);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            $itemType => ['name' => '', 'description' => '', 'rule' => '', 'children' => ['']],
        ]);

        $controller = $this->createController();
        $result = $controller->create(request: $request, itemType: $itemType, indexRouteName: $indexRouteName);

        $this->assertSame($response, $result);
    }

    public function testCreatePostWithNonexistentChildShowsErrorsAndPersistsItem(): void
    {
        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/create', $this->callback(
                static fn(array $params): bool => ($params['data']->errors['children'] ?? []) !== [],
            ))
            ->willReturn($response);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'parent', 'description' => '', 'rule' => '', 'children' => ['missing-child']],
        ]);

        $controller = $this->createController();
        $result = $controller->create(request: $request, itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame($response, $result);
        $this->assertNotNull($this->itemsStorage->getRole('parent'));
    }

    public function testCreatePostWithoutChildrenKeyKeepsDefaultChildren(): void
    {
        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'editor', 'description' => '', 'rule' => ''],
        ]);

        $controller = $this->createController();
        $result = $controller->create(request: $request, itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame($response, $result);
        $this->assertNotNull($this->itemsStorage->getRole('editor'));
    }

    public function testCreatePostWithRoleAsChildShowsErrors(): void
    {
        $this->itemsStorage->add(new Role('some-role'));

        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/create', $this->callback(
                static fn(array $params): bool => ($params['data']->errors['children'] ?? []) !== [],
            ))
            ->willReturn($response);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'permission' => ['name' => 'edit-posts', 'description' => '', 'rule' => '', 'children' => ['some-role']],
        ]);

        $controller = $this->createController();
        $result = $controller->create(request: $request, itemType: 'permission', indexRouteName: 'admin-rbac-permissions');

        $this->assertSame($response, $result);
    }

    public function testCreatePostWithRule(): void
    {
        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->mockRedirectResponse($this->responseFactory);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'permission' => ['name' => 'restricted-action', 'description' => '', 'rule' => 'ownerRule', 'children' => ['']],
        ]);

        $controller = $this->createController();
        $result = $controller->create(request: $request, itemType: 'permission', indexRouteName: 'admin-rbac-permissions');

        $this->assertSame($response, $result);
        $perm = $this->itemsStorage->getPermission('restricted-action');
        $this->assertNotNull($perm);
        $this->assertSame('ownerRule', $perm->getRuleName());
    }

    #[DataProvider('itemTypeProvider')]
    public function testDeleteRemovesItem(string $itemType, string $indexRouteName, string $itemName): void
    {
        $this->addItem($itemType, $itemName);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();
        $result = $controller->delete(new ServerRequest('POST', '/'), $itemName, $itemType, $indexRouteName);

        $this->assertSame($response, $result);
        $this->assertNull($this->getItem($itemType, $itemName));
    }

    #[DataProvider('itemTypeProvider')]
    public function testIndexShowsItems(string $itemType, string $indexRouteName, string $itemName): void
    {
        $this->addItem($itemType, $itemName);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/index', $this->anything())
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index(itemType: $itemType, indexRouteName: $indexRouteName);

        $this->assertSame($response, $result);
    }

    public function testIndexWithFilters(): void
    {
        $this->itemsStorage->add(new Role('admin'));
        $this->itemsStorage->add(new Role('editor'));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index(filterName: 'admin', filterDescription: 'test', itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame($response, $result);
    }

    public function testUpdateGetShowsAvailableChildrenExcludingSelf(): void
    {
        $this->itemsStorage->add(new Role('editor'));
        $this->itemsStorage->add(new Role('other-role'));
        $this->itemsStorage->add(new Permission('some-permission'));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/update', $this->callback(
                static fn(array $params): bool => array_map(static fn($c) => $c->name, $params['data']->children) === ['other-role', 'some-permission'],
            ))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->update(request: new ServerRequest('GET', '/'), name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame($response, $result);
    }

    #[DataProvider('itemTypeProvider')]
    public function testUpdateGetShowsForm(string $itemType, string $indexRouteName, string $itemName): void
    {
        $this->addItem($itemType, $itemName);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/update', $this->anything())
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->update(request: new ServerRequest('GET', '/'), name: $itemName, itemType: $itemType, indexRouteName: $indexRouteName);

        $this->assertSame($response, $result);
    }

    #[DataProvider('itemTypeProvider')]
    public function testUpdateNonExistentShowsError(string $itemType, string $indexRouteName, string $itemName): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->update(request: new ServerRequest('GET', '/'), name: 'nonexistent', itemType: $itemType, indexRouteName: $indexRouteName);

        $this->assertSame($response, $result);
    }

    public function testUpdatePostAssignsAndUnassignsUsers(): void
    {
        $this->itemsStorage->add(new Role('editor'));
        $this->assignmentsStorage->add(new Assignment('1', 'editor', time()));

        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'editor', 'description' => 'Updated', 'rule' => '', 'children' => [''], 'assignedUsers' => ['2']],
        ]);

        $controller = $this->createController();
        $result = $controller->update(request: $request, name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame($response, $result);
        $this->assertNull($this->assignmentsStorage->get('editor', '1'));
        $this->assertNotNull($this->assignmentsStorage->get('editor', '2'));
    }

    #[DataProvider('itemTypeProvider')]
    public function testUpdatePostSuccessful(string $itemType, string $indexRouteName, string $itemName): void
    {
        $this->addItem($itemType, $itemName);

        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->mockRedirectResponse($this->responseFactory);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            $itemType => ['name' => $itemName, 'description' => 'Updated', 'rule' => '', 'children' => [''], 'assignedUsers' => []],
        ]);

        $controller = $this->createController();
        $result = $controller->update(request: $request, name: $itemName, itemType: $itemType, indexRouteName: $indexRouteName);

        $this->assertSame($response, $result);
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
            ResponseFactoryInterface::class => $this->responseFactory,
            FlashInterface::class => $this->flash,
            WebViewRenderer::class => $this->viewRenderer,
            UrlGeneratorInterface::class => new FakeUrlGenerator(),
        ])->get(RbacController::class);
        $controller->update(request: $request, name: $itemName, itemType: $itemType, indexRouteName: $indexRouteName);
    }

    public function testUpdatePostWithChildren(): void
    {
        $this->itemsStorage->add(new Role('editor'));
        $this->itemsStorage->add(new Role('child-role'));

        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'editor', 'description' => '', 'rule' => '', 'children' => ['child-role'], 'assignedUsers' => []],
        ]);

        $controller = $this->createController();
        $result = $controller->update(request: $request, name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame($response, $result);
        $this->assertTrue($this->itemsStorage->hasChild('editor', 'child-role'));
    }

    public function testUpdatePostWithEmptyDescriptionClearsDescription(): void
    {
        $this->itemsStorage->add((new Role('editor'))->withDescription('Original description'));

        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->mockRedirectResponse($this->responseFactory);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'editor', 'description' => '', 'rule' => '', 'children' => [''], 'assignedUsers' => []],
        ]);

        $controller = $this->createController();
        $result = $controller->update(request: $request, name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame($response, $result);
        $role = $this->itemsStorage->getRole('editor');
        $this->assertNotNull($role);
        $this->assertSame('', $role->getDescription());
    }

    public function testUpdatePostWithInvalidDataShowsErrors(): void
    {
        $this->itemsStorage->add(new Role('editor'));

        $validationResult = new Result();
        $validationResult->addError('Name is required.');
        $this->validator->method('validate')->willReturn($validationResult);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/update', $this->callback(
                static fn(array $params): bool => $params['data']->errors !== [],
            ))
            ->willReturn($response);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => '', 'description' => '', 'rule' => '', 'children' => []],
        ]);

        $controller = $this->createController();
        $result = $controller->update(request: $request, name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame($response, $result);
    }

    public function testUpdatePostWithNonexistentChildShowsErrors(): void
    {
        $this->itemsStorage->add(new Role('editor'));

        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/update', $this->callback(
                static fn(array $params): bool => ($params['data']->errors['children'] ?? []) !== [],
            ))
            ->willReturn($response);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'editor', 'description' => 'Updated', 'rule' => '', 'children' => ['missing-child']],
        ]);

        $controller = $this->createController();
        $result = $controller->update(request: $request, name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame($response, $result);
        $role = $this->itemsStorage->getRole('editor');
        $this->assertNotNull($role);
        $this->assertSame('Updated', $role->getDescription());
    }

    #[DataProvider('itemTypeProvider')]
    public function testUpdatePostWithRule(string $itemType, string $indexRouteName, string $itemName): void
    {
        $this->addItem($itemType, $itemName);

        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            $itemType => ['name' => $itemName, 'description' => '', 'rule' => 'someRule', 'children' => [''], 'assignedUsers' => []],
        ]);

        $controller = $this->createController();
        $result = $controller->update(request: $request, name: $itemName, itemType: $itemType, indexRouteName: $indexRouteName);

        $this->assertSame($response, $result);
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

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/update', $this->callback(
                static fn(array $params): bool => count($params['data']->assignedUsers) === 1
                    && $params['data']->assignedUsers[0]->id === (string) $assignedUser->getId(),
            ))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->update(request: new ServerRequest('GET', '/'), name: 'editor', itemType: 'role', indexRouteName: 'admin-rbac-roles');

        $this->assertSame($response, $result);
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
            ResponseFactoryInterface::class => $this->responseFactory,
            FlashInterface::class => $this->flash,
            WebViewRenderer::class => $this->viewRenderer,
        ])->get(RbacController::class);
    }

    private function getItem(string $itemType, string $name): Role|Permission|null
    {
        return $itemType === 'role'
            ? $this->itemsStorage->getRole($name)
            : $this->itemsStorage->getPermission($name);
    }
}
