<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Controller\Admin\Dashboard;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\Admin\Dashboard\DashboardController;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Admin\DashboardService;
use YiiRocks\Voyti\tests\Support\ControllerHarness;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

#[AllowMockObjectsWithoutExpectations]
final class DashboardControllerTest extends TestCase
{
    private ModuleConfig $config;
    private CurrentUser $currentUser;
    private DashboardService&MockObject $dashboardService;
    private FlashInterface&MockObject $flash;
    private ControllerHarness $harness;
    private TranslatorInterface $translator;
    private WebViewRenderer&MockObject $viewRenderer;

    protected function setUp(): void
    {
        $this->config = new ModuleConfig();
        $this->harness = new ControllerHarness($this->config);
        $this->translator = $this->createTranslator();
        $this->viewRenderer = $this->createMock(WebViewRenderer::class);
        $this->flash = $this->createMock(FlashInterface::class);
        $this->dashboardService = $this->createMock(DashboardService::class);
        $this->currentUser = new CurrentUser(
            $this->createMock(IdentityRepositoryInterface::class),
            $this->createMock(EventDispatcherInterface::class),
        );
    }

    public function testIndexRendersDashboardViewWithStats(): void
    {
        $stats = [
            'userTotal' => 10,
            'userBlocked' => 2,
            'userUnconfirmed' => 1,
            'roleCount' => 3,
            'permissionCount' => 4,
            'ruleCount' => 1,
            'recentAuditLogs' => [],
        ];
        $this->dashboardService->method('getStats')->willReturn($stats);

        $controller = $this->createController();

        $response = $this->createMock(ResponseInterface::class);
        $captured = [];
        $this->viewRenderer->method('withViewPath')->willReturnSelf();
        $this->viewRenderer->expects($this->once())
            ->method('render')
            ->with('admin/dashboard/index', $this->callback(function (array $params) use (&$captured): bool {
                $captured = $params;
                return true;
            }))
            ->willReturn($response);

        $result = $controller->index();

        self::assertSame($response, $result);
        self::assertSame($stats, $captured['stats']);
        self::assertSame($this->config, $captured['config']);
        self::assertSame($this->flash, $captured['flash']);
    }

    private function createController(): DashboardController
    {
        return $this->harness->createDashboardController(
            translator: $this->translator,
            viewRenderer: $this->viewRenderer,
            currentUser: $this->currentUser,
            flash: $this->flash,
            dashboardService: $this->dashboardService,
        );
    }
}
