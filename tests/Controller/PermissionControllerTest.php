<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\PermissionController;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Rbac\AssignmentsStorageInterface;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\Rbac\Permission;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class PermissionControllerTest extends TestCase
{
    private AssignmentsStorageInterface $assignmentsStorage;
    private ModuleConfig $config;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private ItemsStorageInterface $itemsStorage;
    private ManagerInterface $manager;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private TranslatorInterface $translator;
    private UserRepository&MockObject $userRepository;
    private ValidatorInterface&MockObject $validator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->config = new ModuleConfig();
        $this->harness = new ControllerHarness($this->config);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->itemsStorage = $this->harness->getItemsStorage();
        $this->assignmentsStorage = $this->harness->getAssignmentsStorage();
        $this->manager = $this->harness->getAuthManager();
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
            ->with('rbac/create', $this->anything())
            ->willReturn($response);

        $result = $controller->create($request);

        $this->assertSame($response, $result);
    }

    public function testCreatePostSuccessful(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['permission' => ['name' => 'edit-posts', 'description' => 'Can edit posts', 'rule' => '', 'children' => ['']]]);

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
        $this->assertNotNull($this->itemsStorage->getPermission('edit-posts'));
    }

    public function testCreatePostWithRule(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['permission' => ['name' => 'restricted-action', 'description' => '', 'rule' => 'ownerRule', 'children' => ['']]]);

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
        $perm = $this->itemsStorage->getPermission('restricted-action');
        $this->assertNotNull($perm);
        $this->assertSame('ownerRule', $perm->getRuleName());
    }

    public function testDeleteRemovesPermission(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Permission('edit-posts'));

        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->delete('edit-posts');

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
            ->with('rbac/index', $this->anything())
            ->willReturn($response);

        $result = $controller->index($request);

        $this->assertSame($response, $result);
    }

    public function testUpdateGetShowsForm(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Permission('edit-posts'));

        $user = $this->createMock(User::class);
        $this->userRepository->method('findByIds')->willReturn([]);

        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('rbac/update', $this->anything())
            ->willReturn($response);

        $result = $controller->update($request, 'edit-posts');

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

    public function testUpdatePostSuccessful(): void
    {
        $controller = $this->createController();
        $this->itemsStorage->add(new Permission('edit-posts'));

        $user = $this->createMock(User::class);
        $this->userRepository->method('findByIds')->willReturn([]);

        $request = (new ServerRequest('POST', '/'))->withParsedBody(['permission' => ['name' => 'edit-posts', 'description' => 'Updated description', 'rule' => '', 'children' => ['']], 'assignedUsers' => []]);

        $this->validator->method('validate')->willReturn(new Result());
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->expects($this->once())
            ->method('createResponse')
            ->with(302)
            ->willReturn($response);
        $response->expects($this->once())
            ->method('withHeader')
            ->willReturnSelf();

        $result = $controller->update($request, 'edit-posts');

        $this->assertSame($response, $result);
    }

    private function createController(): PermissionController
    {
        return $this->harness->createPermissionController(
            userRepository: $this->userRepository,
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            responseFactory: $this->responseFactory,
            flash: $this->flash,
        );
    }
}
