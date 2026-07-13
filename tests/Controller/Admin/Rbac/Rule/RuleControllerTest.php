<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\Rbac\Rule;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\Admin\Rbac\Rule\RuleController;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\AuditLogService;
use YiiRocks\Voyti\Service\Rbac\RuleEditionService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class RuleControllerTest extends TestCase
{
    use RedirectResponseMockTrait;

    private AuditLogService&MockObject $auditLogService;
    private AuthHelper&MockObject $authHelper;
    private ModuleConfig $config;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private RuleEditionService&MockObject $ruleEditionService;
    private TranslatorInterface $translator;
    private ValidatorInterface&MockObject $validator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->config = new ModuleConfig();
        $this->harness = new ControllerHarness($this->config);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->authHelper = $this->createMock(AuthHelper::class);
        $this->ruleEditionService = $this->createMock(RuleEditionService::class);
        $this->auditLogService = $this->createMock(AuditLogService::class);
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
            ->with('admin/rbac/rule/create', $this->anything())
            ->willReturn($response);

        $result = $controller->create($request);

        $this->assertSame($response, $result);
    }

    public function testCreatePostServiceFailsShowsError(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['rule' => ['name' => 'myRule', 'class' => 'Invalid\\Class']]);

        $this->validator->method('validate')->willReturn(new Result());
        $this->ruleEditionService->expects($this->once())
            ->method('create')
            ->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->create($request);

        $this->assertSame($response, $result);
    }

    public function testCreatePostSuccessful(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['rule' => ['name' => 'myRule', 'class' => 'App\\Rule\\MyRule']]);

        $this->validator->method('validate')->willReturn(new Result());
        $this->ruleEditionService->expects($this->once())
            ->method('create')
            ->willReturn(true);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->create($request);

        $this->assertSame($response, $result);
    }

    public function testCreatePostWithInvalidDataShowsErrors(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['rule' => ['name' => '', 'class' => '']]);

        $result = new Result();
        $result->addError('Name is required.');
        $this->validator->method('validate')->willReturn($result);
        $this->ruleEditionService->expects($this->never())->method('create');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/rule/create', $this->callback(
                static fn (array $params): bool => $params['errors'] !== [],
            ))
            ->willReturn($response);

        $result2 = $controller->create($request);

        $this->assertSame($response, $result2);
    }

    public function testDeleteRemovesRule(): void
    {
        $controller = $this->createController();

        $this->ruleEditionService->expects($this->once())->method('remove')->with('myRule');

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->delete('myRule');

        $this->assertSame($response, $result);
    }

    public function testIndexShowsRules(): void
    {
        $controller = $this->createController();

        $this->authHelper->method('getRuleNames')->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/rule/index', $this->anything())
            ->willReturn($response);

        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testUpdateGetShowsForm(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/rule/update', $this->anything())
            ->willReturn($response);

        $result = $controller->update($request, 'existingRule');

        $this->assertSame($response, $result);
    }

    public function testUpdatePostServiceFailsShowsError(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['rule' => ['name' => 'updatedRule', 'class' => 'Invalid\\Class']]);

        $this->validator->method('validate')->willReturn(new Result());
        $this->ruleEditionService->expects($this->once())
            ->method('update')
            ->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->willReturn($response);

        $result = $controller->update($request, 'oldRule');

        $this->assertSame($response, $result);
    }

    public function testUpdatePostSuccessful(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['rule' => ['name' => 'updatedRule', 'class' => 'App\\Rule\\UpdatedRule']]);

        $this->validator->method('validate')->willReturn(new Result());
        $this->ruleEditionService->expects($this->once())
            ->method('update')
            ->willReturn(true);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $result = $controller->update($request, 'oldRule');

        $this->assertSame($response, $result);
    }

    public function testUpdatePostWithInvalidDataShowsErrors(): void
    {
        $controller = $this->createController();
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['rule' => ['name' => '', 'class' => '']]);

        $result = new Result();
        $result->addError('Name is required.');
        $this->validator->method('validate')->willReturn($result);
        $this->ruleEditionService->expects($this->never())->method('update');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/rule/update', $this->callback(
                static fn (array $params): bool => $params['errors'] !== [],
            ))
            ->willReturn($response);

        $result2 = $controller->update($request, 'oldRule');

        $this->assertSame($response, $result2);
    }

    private function createController(): RuleController
    {
        return $this->harness->createRuleController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            validator: $this->validator,
            responseFactory: $this->responseFactory,
            flash: $this->flash,
            authHelper: $this->authHelper,
            ruleEditionService: $this->ruleEditionService,
            auditLogService: $this->auditLogService,
        );
    }
}
