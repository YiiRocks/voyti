<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\Rbac\Permission;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use YiiRocks\Voyti\Controller\Admin\Rbac\RbacController;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\AuditLogService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Auth\IdentityRepositoryInterface;
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
final class PermissionControllerTest extends TestCase
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
                static fn(array $params): bool => array_keys($params['availableChildren']) === ['other-permission'],
            ))
            ->willReturn($response);

        $result = $controller->create($request, 'permission', 'admin-rbac-permissions');

        $this->assertSame($response, $result);
    }

    public function testCreateGetShowsForm(): void
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

        $result = $controller->create($request, 'permission', 'admin-rbac-permissions');

        $this->assertSame($response, $result);
    }

    public function testCreatePostSuccessful(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['permission' => ['name' => 'edit-posts', 'description' => 'Can edit posts', 'rule' => '', 'children' => ['']]]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->create($request, 'permission', 'admin-rbac-permissions');

        $this->assertSame($response, $result);
        $this->assertNotNull($this->itemsStorage->getPermission('edit-posts'));
    }

    public function testCreatePostWithInvalidDataShowsErrors(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['permission' => ['name' => '', 'description' => '', 'rule' => '', 'children' => ['']]]);

        $result = new Result();
        $result->addError('Name is required.');
        $this->validator->method('validate')->willReturn($result);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/create', $this->callback(
                static fn(array $params): bool => $params['errors'] !== [],
            ))
            ->willReturn($response);

        $result2 = $controller->create($request, 'permission', 'admin-rbac-permissions');

        $this->assertSame($response, $result2);
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
                static fn(array $params): bool => ($params['errors']['children'] ?? []) !== [],
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

    public function testDeleteRemovesPermission(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Permission('edit-posts'));

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->delete('edit-posts', 'permission', 'admin-rbac-permissions');

        $this->assertSame($response, $result);
        $this->assertNull($this->itemsStorage->getPermission('edit-posts'));
    }

    public function testIndexShowsPermissions(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->itemsStorage->add(new Permission('view-dashboard'));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/index', $this->anything())
            ->willReturn($response);

        $result = $controller->index($request, 'permission', 'admin-rbac-permissions');

        $this->assertSame($response, $result);
    }

    public function testUpdateGetShowsAvailableChildrenExcludingRolesAndSelf(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Permission('edit-posts'));
        $this->itemsStorage->add(new Permission('other-permission'));
        $this->itemsStorage->add(new Role('some-role'));

        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/update', $this->callback(
                static fn(array $params): bool => array_keys($params['availableChildren']) === ['other-permission'],
            ))
            ->willReturn($response);

        $result = $controller->update($request, 'edit-posts', 'permission', 'admin-rbac-permissions');

        $this->assertSame($response, $result);
    }

    public function testUpdateGetShowsForm(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Permission('edit-posts'));

        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/update', $this->anything())
            ->willReturn($response);

        $result = $controller->update($request, 'edit-posts', 'permission', 'admin-rbac-permissions');

        $this->assertSame($response, $result);
    }

    public function testUpdateNonExistentShowsError(): void
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

        $result = $controller->update($request, 'nonexistent', 'permission', 'admin-rbac-permissions');

        $this->assertSame($response, $result);
    }

    public function testUpdatePostSuccessful(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Permission('edit-posts'));

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['permission' => ['name' => 'edit-posts', 'description' => 'Updated description', 'rule' => '', 'children' => ['']], 'assignedUsers' => []]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->update($request, 'edit-posts', 'permission', 'admin-rbac-permissions');

        $this->assertSame($response, $result);
    }

    public function testUpdatePostThrowsWhenPermissionMissingFromItemsStorage(): void
    {
        $managerOnlyStorage = new SimpleItemsStorage();
        $managerOnlyStorage->add(new Permission('edit-posts'));
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

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['permission' => ['name' => 'edit-posts', 'description' => '', 'rule' => '', 'children' => ['']]]);
        $this->validator->method('validate')->willReturn(new Result());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Permission 'edit-posts' not found.");

        $controller->update($request, 'edit-posts', 'permission', 'admin-rbac-permissions');
    }

    public function testUpdatePostWithRule(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Permission('edit-posts'));

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['permission' => ['name' => 'edit-posts', 'description' => '', 'rule' => 'someRule', 'children' => ['']], 'assignedUsers' => []]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $result = $controller->update($request, 'edit-posts', 'permission', 'admin-rbac-permissions');

        $this->assertSame($response, $result);
        $perm = $this->itemsStorage->getPermission('edit-posts');
        $this->assertNotNull($perm);
        $this->assertSame('someRule', $perm->getRuleName());
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
}
