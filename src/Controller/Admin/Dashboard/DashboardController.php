<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Admin\Dashboard;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Admin\DashboardService;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class DashboardController
{
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private ModuleConfig $config,
        private DashboardService $dashboardService,
        private FlashInterface $flash,
    ) {
    }

    public function index(): ResponseInterface
    {
        return $this->renderView('admin/dashboard/index', [
            'stats' => $this->dashboardService->getStats(),
            'config' => $this->config,
            'flash' => $this->flash,
        ]);
    }
}
