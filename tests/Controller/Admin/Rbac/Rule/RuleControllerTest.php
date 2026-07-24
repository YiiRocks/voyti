<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\Rbac\Rule;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\Admin\Rbac\Rule\RuleController;
use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\AuditLogService;
use YiiRocks\Voyti\Service\Rbac\RuleEditionService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\Support\RedirectResponseMockTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
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
        $this->config = ModuleConfigFactory::create();
        $this->harness = new ControllerHarness($this->config);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->viewRenderer->method('withAddedInjections')->willReturnSelf();
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->authHelper = $this->createMock(AuthHelper::class);
        $this->ruleEditionService = $this->createMock(RuleEditionService::class);
        $this->auditLogService = $this->createMock(AuditLogService::class);
    }

    public function testCreateGetShowsForm(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/rule/create', $this->anything())
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->create(request: new ServerRequest('GET', '/'));

        $this->assertSame($response, $result);
    }

    public function testCreatePostServiceFailsShowsError(): void
    {
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

        $controller = $this->createController();
        $result = $controller->create(request: new ServerRequest('POST', '/'), ruleName: 'myRule', ruleClass: 'Invalid\\Class');

        $this->assertSame($response, $result);
    }

    public function testCreatePostSuccessful(): void
    {
        $this->validator->method('validate')->willReturn(new Result());
        $this->ruleEditionService->expects($this->once())
            ->method('create')
            ->willReturn(true);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();
        $result = $controller->create(request: new ServerRequest('POST', '/'), ruleName: 'myRule', ruleClass: 'App\\Rule\\MyRule');

        $this->assertSame($response, $result);
    }

    public function testCreatePostWithInvalidDataShowsErrors(): void
    {
        $validationResult = new Result();
        $validationResult->addError('Name is required.');
        $this->validator->method('validate')->willReturn($validationResult);
        $this->ruleEditionService->expects($this->never())->method('create');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/rule/create', $this->callback(
                static fn(array $params): bool => $params['data']->errors !== [],
            ))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->create(request: new ServerRequest('POST', '/'), ruleName: '', ruleClass: '');

        $this->assertSame($response, $result);
    }

    public function testDeleteRemovesRule(): void
    {
        $this->ruleEditionService->expects($this->once())->method('remove')->with('myRule');

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();
        $result = $controller->delete('myRule');

        $this->assertSame($response, $result);
    }

    public function testIndexShowsRules(): void
    {
        $this->authHelper->method('getRuleNames')->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/rule/index', $this->anything())
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->index();

        $this->assertSame($response, $result);
    }

    public function testUpdateGetShowsForm(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/rule/update', $this->anything())
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->update(request: new ServerRequest('GET', '/'), name: 'existingRule');

        $this->assertSame($response, $result);
    }

    public function testUpdatePostServiceFailsShowsError(): void
    {
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

        $controller = $this->createController();
        $result = $controller->update(request: new ServerRequest('POST', '/'), name: 'oldRule', ruleName: 'updatedRule', ruleClass: 'Invalid\\Class');

        $this->assertSame($response, $result);
    }

    public function testUpdatePostSuccessful(): void
    {
        $this->validator->method('validate')->willReturn(new Result());
        $this->ruleEditionService->expects($this->once())
            ->method('update')
            ->willReturn(true);

        $response = $this->mockRedirectResponse($this->responseFactory);

        $controller = $this->createController();
        $result = $controller->update(request: new ServerRequest('POST', '/'), name: 'oldRule', ruleName: 'updatedRule', ruleClass: 'App\\Rule\\UpdatedRule');

        $this->assertSame($response, $result);
    }

    public function testUpdatePostWithInvalidDataShowsErrors(): void
    {
        $validationResult = new Result();
        $validationResult->addError('Name is required.');
        $this->validator->method('validate')->willReturn($validationResult);
        $this->ruleEditionService->expects($this->never())->method('update');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/rbac/rule/update', $this->callback(
                static fn(array $params): bool => $params['data']->errors !== [],
            ))
            ->willReturn($response);

        $controller = $this->createController();
        $result = $controller->update(request: new ServerRequest('POST', '/'), name: 'oldRule', ruleName: '', ruleClass: '');

        $this->assertSame($response, $result);
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
