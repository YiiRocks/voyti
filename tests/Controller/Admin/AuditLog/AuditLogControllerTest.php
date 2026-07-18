<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\AuditLog;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\Admin\AuditLog\AuditLogController;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class AuditLogControllerTest extends TestCase
{
    use DatabaseSetupTrait;

    private ModuleConfig $config;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private ResponseFactoryInterface&MockObject $responseFactory;
    private TranslatorInterface $translator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->config = new ModuleConfig();
        $this->harness = new ControllerHarness($this->config);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $this->flash = $this->createMock(FlashInterface::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testIndexFiltersByAction(): void
    {
        $this->createLog(1, 2, 'user.create');
        $this->createLog(1, 2, 'user.delete');

        $controller = $this->createController();
        $request = (new ServerRequest('GET', '/'))->withQueryParams(['action' => 'create']);

        $captured = [];
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturnCallback(
            function (string $view, array $params) use (&$captured, $response): ResponseInterface {
                $captured = $params;
                return $response;
            },
        );

        $controller->index($request);

        $this->assertCount(1, $captured['logs']);
    }

    public function testIndexPresentsLogFieldsFormattedForDisplay(): void
    {
        $log = new UserAuditLog();
        $log->setActorUserId(1);
        $log->setAction('rbac.role.update');
        $log->setTargetName('editor');
        $log->setTargetUserId(7);
        $log->setContext('{"previousName":"old-editor"}');
        $log->setCreatedAt(1700000000);
        $log->save();

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $captured = [];
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturnCallback(
            function (string $view, array $params) use (&$captured, $response): ResponseInterface {
                $captured = $params;
                return $response;
            },
        );

        $controller->index($request);

        self::assertSame(
            [
                'createdAt' => TimezoneHelper::formatLocalized(1700000000, $this->translator->getLocale()),
                'actorUserId' => '1',
                'action' => 'rbac.role.update',
                'targetLabel' => 'editor (#7)',
                'context' => '{"previousName":"old-editor"}',
            ],
            $captured['logs'][0],
        );
    }

    public function testIndexPresentsLogWithoutTargetOrContext(): void
    {
        $log = new UserAuditLog();
        $log->setAction('system.cleanup');
        $log->setCreatedAt(1700000000);
        $log->save();

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $captured = [];
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturnCallback(
            function (string $view, array $params) use (&$captured, $response): ResponseInterface {
                $captured = $params;
                return $response;
            },
        );

        $controller->index($request);

        self::assertSame('', $captured['logs'][0]['actorUserId']);
        self::assertSame('', $captured['logs'][0]['targetLabel']);
        self::assertSame('', $captured['logs'][0]['context']);
    }

    public function testIndexShowsLogs(): void
    {
        $this->createLog(1, 2, 'user.create');

        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->expects($this->once())
            ->method('withViewPath')
            ->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/audit-log/index', $this->anything())
            ->willReturn($response);

        $result = $controller->index($request);

        $this->assertSame($response, $result);
    }

    public function testIndexWithNoResultsPaginatorHasNoPages(): void
    {
        $controller = $this->createController();
        $request = new ServerRequest('GET', '/');

        $captured = [];
        $response = $this->createMock(ResponseInterface::class);
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->method('render')->willReturnCallback(
            function (string $view, array $params) use (&$captured, $response): ResponseInterface {
                $captured = $params;
                return $response;
            },
        );

        $controller->index($request);

        $this->assertInstanceOf(OffsetPaginator::class, $captured['paginator']);
        $this->assertSame(0, $captured['paginator']->getTotalPages());
    }

    private function createController(): AuditLogController
    {
        return $this->harness->createAuditLogController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            responseFactory: $this->responseFactory,
            flash: $this->flash,
        );
    }

    private function createLog(int $actorUserId, int $targetUserId, string $action): void
    {
        $log = new UserAuditLog();
        $log->setActorUserId($actorUserId);
        $log->setTargetUserId($targetUserId);
        $log->setAction($action);
        $log->setCreatedAt(time());
        $log->save();
    }
}
