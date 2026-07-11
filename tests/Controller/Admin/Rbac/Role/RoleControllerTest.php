<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\Rbac\Role;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use YiiRocks\Voyti\Controller\Admin\Rbac\Role\RoleController;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\Support\SimpleItemsStorage;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Role;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class RoleControllerTest extends TestCase
{
    use DatabaseSetupTrait;

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

        $result = $controller->create($request);

        $this->assertSame($response, $result);
    }

    public function testCreatePostSuccessful(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['role' => ['name' => 'editor', 'description' => 'Editors', 'rule' => '', 'children' => ['']]]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->create($request);

        $this->assertSame($response, $result);
        $this->assertNotNull($this->itemsStorage->getRole('editor'));
    }

    public function testCreatePostWithChildren(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Role('child-role'));
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['role' => ['name' => 'parent', 'description' => '', 'rule' => '', 'children' => ['child-role']]]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->create($request);

        $this->assertSame($response, $result);
        $this->assertTrue($this->itemsStorage->hasChild('parent', 'child-role'));
    }

    public function testCreatePostWithInvalidDataShowsErrors(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['role' => ['name' => '', 'description' => '', 'rule' => '', 'children' => ['']]]);

        $result = new Result();
        $result->addError('Name is required.');
        $this->validator->method('validate')->willReturn($result);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/create', $this->callback(
                static fn (array $params): bool => $params['errors'] !== [],
            ))
            ->willReturn($response);

        $result2 = $controller->create($request);

        $this->assertSame($response, $result2);
    }

    public function testCreatePostWithoutChildrenKeyKeepsDefaultChildren(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['role' => ['name' => 'editor', 'description' => '', 'rule' => '']]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $result = $controller->create($request);

        $this->assertSame($response, $result);
        $this->assertNotNull($this->itemsStorage->getRole('editor'));
    }

    public function testDeleteRemovesRole(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Role('editor'));

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->delete('editor');

        $this->assertSame($response, $result);
        $this->assertNull($this->itemsStorage->getRole('editor'));
    }

    public function testIndexShowsRoles(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $this->itemsStorage->add(new Role('admin'));

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/index', $this->anything())
            ->willReturn($response);

        $result = $controller->index($request);

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

        $result = $controller->index($request);

        $this->assertSame($response, $result);
    }

    public function testUpdateGetShowsForm(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Role('editor'));

        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/update', $this->anything())
            ->willReturn($response);

        $result = $controller->update($request, 'editor');

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

        $result = $controller->update($request, 'nonexistent');

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

        $result = $controller->update($request, 'editor');

        $this->assertSame($response, $result);
        $this->assertNull($this->assignmentsStorage->get('editor', '1'));
        $this->assertNotNull($this->assignmentsStorage->get('editor', '2'));
    }

    public function testUpdatePostSuccessful(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Role('editor'));

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['role' => ['name' => 'editor', 'description' => 'Updated', 'rule' => '', 'children' => ['']], 'assignedUsers' => []]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->update($request, 'editor');

        $this->assertSame($response, $result);
    }

    public function testUpdatePostThrowsWhenRoleMissingFromItemsStorage(): void
    {
        $managerOnlyStorage = new SimpleItemsStorage();
        $managerOnlyStorage->add(new Role('editor'));
        $manager = new Manager($managerOnlyStorage, $this->assignmentsStorage);

        $controller = new RoleController(
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
        );

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['role' => ['name' => 'editor', 'description' => '', 'rule' => '', 'children' => ['']]]);
        $this->validator->method('validate')->willReturn(new Result());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Role 'editor' not found.");

        $controller->update($request, 'editor');
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

        $result = $controller->update($request, 'editor');

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
                static fn (array $params): bool => $params['errors'] !== [],
            ))
            ->willReturn($response);

        $result2 = $controller->update($request, 'editor');

        $this->assertSame($response, $result2);
    }

    public function testUpdatePostWithRule(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Role('editor'));

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['role' => ['name' => 'editor', 'description' => '', 'rule' => 'someRule', 'children' => ['']], 'assignedUsers' => []]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')->willReturn($response);
        $response->method('withHeader')->willReturnSelf();

        $result = $controller->update($request, 'editor');

        $this->assertSame($response, $result);
        $role = $this->itemsStorage->getRole('editor');
        $this->assertNotNull($role);
        $this->assertSame('someRule', $role->getRuleName());
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
                static fn (array $params): bool => count($params['users']) === 1
                    && $params['users'][0]->getId() === $assignedUser->getId(),
            ))
            ->willReturn($response);

        $result = $controller->update($request, 'editor');

        $this->assertSame($response, $result);
    }

    private function createController(): RoleController
    {
        return $this->harness->createRoleController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            responseFactory: $this->responseFactory,
            flash: $this->flash,
        );
    }
}
