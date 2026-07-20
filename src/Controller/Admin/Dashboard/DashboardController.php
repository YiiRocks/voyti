<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Admin\Dashboard;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Admin\DashboardService;
use YiiRocks\Voyti\ViewData\Admin\Dashboard\IndexViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Renders the admin dashboard landing page, delegating stat aggregation to {@see DashboardService}.
 */
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
        private CurrentUser $currentUser,
    ) {}

    public function index(): ResponseInterface
    {
        $viewer = $this->currentUser->getIdentity();
        $viewerTimezone = $viewer instanceof User ? $viewer->getProfile()?->getTimezone() : null;

        return $this->renderView('admin/dashboard/index', [
            'data' => IndexViewData::create(
                $this->dashboardService->getStats($viewerTimezone),
                $this->url,
                $this->translator(),
            ),
        ]);
    }
}
