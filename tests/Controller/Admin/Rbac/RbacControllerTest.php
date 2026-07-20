<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\Rbac;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use YiiRocks\Voyti\Controller\Admin\Rbac\RbacController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\AuditLogService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class RbacControllerTest extends TestCase
{
    use DatabaseSetupTrait;
    use RedirectResponseMockTrait;

    private AssignmentsStorageInterface $assignmentsStorage;
    private ModuleConfig $config;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private ItemsStorageInterface $itemsStorage;
    private ManagerInterface $manager;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private TranslatorInterface $translator;
    private ValidatorInterface&MockObject $validator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->config = new ModuleConfig();
        $this->harness = new ControllerHarness($this->config);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->itemsStorage = $this->harness->getItemsStorage();
        $this->assignmentsStorage = $this->harness->getAssignmentsStorage();
        $this->manager = $this->harness->getAuthManager();
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
        $controller = $this->createController();
        $this->itemsStorage->add(new Permission('other-permission'));
        $this->itemsStorage->add(new Role('some-role'));

        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/create', $this->callback(
                static fn(array $params): bool => array_map(static fn($c) => $c->name, $params['data']->children) === ['other-permission'],
            ))
            ->willReturn($response);

        $result = $controller->create($request, 'permission', 'admin-rbac-permissions');

        $this->assertSame($response, $result);
    }

    public function testCreateGetShowsAvailableChildrenIncludingRolesAndPermissions(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Role('other-role'));
        $this->itemsStorage->add(new Permission('some-permission'));
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/create', $this->callback(
                static fn(array $params): bool => array_map(static fn($c) => $c->name, $params['data']->children) === ['other-role', 'some-permission'],
            ))
            ->willReturn($response);

        $result = $controller->create($request, 'role', 'admin-rbac-roles');

        $this->assertSame($response, $result);
    }

    #[DataProvider('itemTypeProvider')]
    public function testCreateGetShowsForm(string $itemType, string $indexRouteName, string $itemName): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/create', $this->anything())
            ->willReturn($response);

        $result = $controller->create($request, $itemType, $indexRouteName);

        $this->assertSame($response, $result);
    }

    #[DataProvider('itemTypeProvider')]
    public function testCreatePostSuccessful(string $itemType, string $indexRouteName, string $itemName): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody([$itemType => ['name' => $itemName, 'description' => '', 'rule' => '', 'children' => ['']]]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->create($request, $itemType, $indexRouteName);

        $this->assertSame($response, $result);
        $this->assertNotNull($this->getItem($itemType, $itemName));
    }

    public function testCreatePostWithChildren(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Role('child-role'));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['role' => ['name' => 'parent', 'description' => '', 'rule' => '', 'children' => ['child-role']]]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->create($request, 'role', 'admin-rbac-roles');

        $this->assertSame($response, $result);
        $this->assertTrue($this->itemsStorage->hasChild('parent', 'child-role'));
    }

    #[DataProvider('itemTypeProvider')]
    public function testCreatePostWithInvalidDataShowsErrors(string $itemType, string $indexRouteName, string $itemName): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody([$itemType => ['name' => '', 'description' => '', 'rule' => '', 'children' => ['']]]);

        $result = new Result();
        $result->addError('Name is required.');
        $this->validator->method('validate')->willReturn($result);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/create', $this->callback(
                static fn(array $params): bool => $params['data']->errors !== [],
            ))
            ->willReturn($response);

        $result2 = $controller->create($request, $itemType, $indexRouteName);

        $this->assertSame($response, $result2);
    }

    public function testCreatePostWithNonexistentChildShowsErrorsAndPersistsItem(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['role' => ['name' => 'parent', 'description' => '', 'rule' => '', 'children' => ['missing-child']]]);

        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/create', $this->callback(
                static fn(array $params): bool => ($params['data']->errors['children'] ?? []) !== [],
            ))
            ->willReturn($response);

        $result = $controller->create($request, 'role', 'admin-rbac-roles');

        $this->assertSame($response, $result);
        $this->assertNotNull($this->itemsStorage->getRole('parent'));
    }

    public function testCreatePostWithoutChildrenKeyKeepsDefaultChildren(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['role' => ['name' => 'editor', 'description' => '', 'rule' => '']]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $result = $controller->create($request, 'role', 'admin-rbac-roles');

        $this->assertSame($response, $result);
        $this->assertNotNull($this->itemsStorage->getRole('editor'));
    }

    public function testCreatePostWithRoleAsChildShowsErrors(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Role('some-role'));

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['permission' => ['name' => 'edit-posts', 'description' => '', 'rule' => '', 'children' => ['some-role']]]);

        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/create', $this->callback(
                static fn(array $params): bool => ($params['data']->errors['children'] ?? []) !== [],
            ))
            ->willReturn($response);

        $result = $controller->create($request, 'permission', 'admin-rbac-permissions');

        $this->assertSame($response, $result);
    }

    public function testCreatePostWithRule(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['permission' => ['name' => 'restricted-action', 'description' => '', 'rule' => 'ownerRule', 'children' => ['']]]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->create($request, 'permission', 'admin-rbac-permissions');

        $this->assertSame($response, $result);
        $perm = $this->itemsStorage->getPermission('restricted-action');
        $this->assertNotNull($perm);
        $this->assertSame('ownerRule', $perm->getRuleName());
    }

    #[DataProvider('itemTypeProvider')]
    public function testDeleteRemovesItem(string $itemType, string $indexRouteName, string $itemName): void
    {
        $controller = $this->createController();
        $this->addItem($itemType, $itemName);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->delete($itemName, $itemType, $indexRouteName);

        $this->assertSame($response, $result);
        $this->assertNull($this->getItem($itemType, $itemName));
    }

    #[DataProvider('itemTypeProvider')]
    public function testIndexShowsItems(string $itemType, string $indexRouteName, string $itemName): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->addItem($itemType, $itemName);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/index', $this->anything())
            ->willReturn($response);

        $result = $controller->index($request, $itemType, $indexRouteName);

        $this->assertSame($response, $result);
    }

    public function testIndexWithFilters(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/?name=admin&description=test');

        $this->itemsStorage->add(new Role('admin'));
        $this->itemsStorage->add(new Role('editor'));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->index($request, 'role', 'admin-rbac-roles');

        $this->assertSame($response, $result);
    }

    public function testUpdateGetShowsAvailableChildrenExcludingSelf(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Role('editor'));
        $this->itemsStorage->add(new Role('other-role'));
        $this->itemsStorage->add(new Permission('some-permission'));

        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/update', $this->callback(
                static fn(array $params): bool => array_map(static fn($c) => $c->name, $params['data']->children) === ['other-role', 'some-permission'],
            ))
            ->willReturn($response);

        $result = $controller->update($request, 'editor', 'role', 'admin-rbac-roles');

        $this->assertSame($response, $result);
    }

    #[DataProvider('itemTypeProvider')]
    public function testUpdateGetShowsForm(string $itemType, string $indexRouteName, string $itemName): void
    {
        $controller = $this->createController();
        $this->addItem($itemType, $itemName);

        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/update', $this->anything())
            ->willReturn($response);

        $result = $controller->update($request, $itemName, $itemType, $indexRouteName);

        $this->assertSame($response, $result);
    }

    #[DataProvider('itemTypeProvider')]
    public function testUpdateNonExistentShowsError(string $itemType, string $indexRouteName, string $itemName): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->update($request, 'nonexistent', $itemType, $indexRouteName);

        $this->assertSame($response, $result);
    }

    public function testUpdatePostAssignsAndUnassignsUsers(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Role('editor'));
        $this->assignmentsStorage->add(new Assignment('1', 'editor', time()));

        $request = (new ServerRequest('POST', '/'))->withParsedBody([
            'role' => ['name' => 'editor', 'description' => 'Updated', 'rule' => '', 'children' => ['']],
            'assignedUsers' => ['2'],
        ]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $result = $controller->update($request, 'editor', 'role', 'admin-rbac-roles');

        $this->assertSame($response, $result);
        $this->assertNull($this->assignmentsStorage->get('editor', '1'));
        $this->assertNotNull($this->assignmentsStorage->get('editor', '2'));
    }

    #[DataProvider('itemTypeProvider')]
    public function testUpdatePostSuccessful(string $itemType, string $indexRouteName, string $itemName): void
    {
        $controller = $this->createController();
        $this->addItem($itemType, $itemName);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([$itemType => ['name' => $itemName, 'description' => 'Updated', 'rule' => '', 'children' => ['']], 'assignedUsers' => []]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->update($request, $itemName, $itemType, $indexRouteName);

        $this->assertSame($response, $result);
    }

    #[DataProvider('itemTypeProvider')]
    public function testUpdatePostThrowsWhenItemMissingFromItemsStorage(string $itemType, string $indexRouteName, string $itemName): void
    {
        $managerOnlyStorage = new SimpleItemsStorage();
        $item = $itemType === 'role' ? new Role($itemName) : new Permission($itemName);
        $managerOnlyStorage->add($item);
        $manager = new Manager($managerOnlyStorage, $this->assignmentsStorage);

        $controller = new RbacController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            url: $this->harness->getUrlGenerator(),
            validator: $this->validator,
            responseFactory: $this->responseFactory,
            itemsStorage: $this->itemsStorage,
            managerInterface: $manager,
            assignmentsStorage: $this->assignmentsStorage,
            flash: $this->flash,
            config: $this->config,
            auditLogService: $this->createMock(AuditLogService::class),
            currentUser: new CurrentUser(
                $this->createMock(IdentityRepositoryInterface::class),
                $this->createMock(EventDispatcherInterface::class),
            ),
        );

        $request = (new ServerRequest('POST', '/'))->withParsedBody([$itemType => ['name' => $itemName, 'description' => '', 'rule' => '', 'children' => ['']]]);
        $this->validator->method('validate')->willReturn(new Result());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(ucfirst($itemType) . " '$itemName' not found.");

        $controller->update($request, $itemName, $itemType, $indexRouteName);
    }

    public function testUpdatePostWithChildren(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Role('editor'));
        $this->itemsStorage->add(new Role('child-role'));

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['role' => ['name' => 'editor', 'description' => '', 'rule' => '', 'children' => ['child-role']], 'assignedUsers' => []]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $result = $controller->update($request, 'editor', 'role', 'admin-rbac-roles');

        $this->assertSame($response, $result);
        $this->assertTrue($this->itemsStorage->hasChild('editor', 'child-role'));
    }

    public function testUpdatePostWithInvalidDataShowsErrors(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Role('editor'));

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['role' => ['name' => '', 'description' => '', 'rule' => '', 'children' => ['']]]);

        $result = new Result();
        $result->addError('Name is required.');
        $this->validator->method('validate')->willReturn($result);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/update', $this->callback(
                static fn(array $params): bool => $params['data']->errors !== [],
            ))
            ->willReturn($response);

        $result2 = $controller->update($request, 'editor', 'role', 'admin-rbac-roles');

        $this->assertSame($response, $result2);
    }

    public function testUpdatePostWithNonexistentChildShowsErrors(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Role('editor'));

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['role' => ['name' => 'editor', 'description' => 'Updated', 'rule' => '', 'children' => ['missing-child']]]);

        $this->validator->method('validate')->willReturn(new Result());

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/update', $this->callback(
                static fn(array $params): bool => ($params['data']->errors['children'] ?? []) !== [],
            ))
            ->willReturn($response);

        $result = $controller->update($request, 'editor', 'role', 'admin-rbac-roles');

        $this->assertSame($response, $result);
        $role = $this->itemsStorage->getRole('editor');
        $this->assertNotNull($role);
        $this->assertSame('Updated', $role->getDescription());
    }

    #[DataProvider('itemTypeProvider')]
    public function testUpdatePostWithRule(string $itemType, string $indexRouteName, string $itemName): void
    {
        $controller = $this->createController();
        $this->addItem($itemType, $itemName);

        $request = (new ServerRequest('POST', '/'))->withParsedBody([$itemType => ['name' => $itemName, 'description' => '', 'rule' => 'someRule', 'children' => ['']], 'assignedUsers' => []]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $result = $controller->update($request, $itemName, $itemType, $indexRouteName);

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

        $controller = $this->createController();
        $this->itemsStorage->add(new Role('editor'));
        $this->assignmentsStorage->add(new Assignment((string) $assignedUser->getId(), 'editor', time()));

        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/update', $this->callback(
                static fn(array $params): bool => count($params['data']->assignedUsers) === 1
                    && $params['data']->assignedUsers[0]->id === (string) $assignedUser->getId(),
            ))
            ->willReturn($response);

        $result = $controller->update($request, 'editor', 'role', 'admin-rbac-roles');

        $this->assertSame($response, $result);
    }

    private function addItem(string $itemType, string $name): void
    {
        $item = $itemType === 'role' ? new Role($name) : new Permission($name);
        $this->itemsStorage->add($item);
    }

    private function createController(): RbacController
    {
        return $this->harness->createRbacController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            responseFactory: $this->responseFactory,
            flash: $this->flash,
        );
    }

    private function getItem(string $itemType, string $name): Role|Permission|null
    {
        return $itemType === 'role'
            ? $this->itemsStorage->getRole($name)
            : $this->itemsStorage->getPermission($name);
    }
}
