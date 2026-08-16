<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Controller\Admin\Dashboard;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\Views\MenuView;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Admin\DashboardService;
use YiiRocks\Voyti\Service\FlashNotifier;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\UrlGeneratorInterface;
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
        private VoytiConfig $config,
        private DashboardService $dashboardService,
        private FlashNotifier $flashNotifier,
        private CurrentUser $currentUser,
    ) {}

    public function index(): ResponseInterface
    {
        $viewer = $this->currentUser->getIdentity();
        $viewerTimezone = $viewer instanceof User ? $viewer->getProfile()?->getTimezone() : null;
        $stats = $this->dashboardService->getStats($viewerTimezone);

        return $this->renderView('admin/dashboard/index', [
            'data' => [
                'menu' => MenuView::admin($this->url, $this->translator()),
                'tiles' => $this->buildTiles($stats),
                'trendWidgets' => $this->buildTrendWidgets($stats),
                'recommendedPackages' => $stats['recommendedPackages'],
                'recentAuditLogs' => $stats['recentAuditLogs'],
                'auditLogUrl' => $this->url->generate('voyti/admin-audit-log'),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $stats
     * @return list<array{labelKey: string, value: mixed, url: string, borderClass: string}>
     */
    private function buildTiles(array $stats): array
    {
        $tiles = [
            $this->tile('voyti.view.dashboard.users_total', $stats['userTotal'], 'voyti/admin-users', 'border-primary'),
            $this->tile('voyti.view.dashboard.users_blocked', $stats['userBlocked'], 'voyti/admin-users', 'border-danger'),
        ];
        if ($stats['userUnconfirmed'] !== null) {
            $tiles[] = $this->tile('voyti.view.dashboard.users_unconfirmed', $stats['userUnconfirmed'], 'voyti/admin-users', 'border-warning');
        }
        $tiles[] = $this->tile('voyti.view.dashboard.roles', $stats['roleCount'], 'voyti/admin-rbac-roles', 'border-secondary');
        $tiles[] = $this->tile('voyti.view.dashboard.permissions', $stats['permissionCount'], 'voyti/admin-rbac-permissions', 'border-secondary');
        $tiles[] = $this->tile('voyti.view.dashboard.rules', $stats['ruleCount'], 'voyti/admin-rbac-rules', 'border-secondary');
        return $tiles;
    }

    /**
     * @param array<string, mixed> $stats
     * @return list<array{titleKey: string, periods: list<array{labelKey: string, value: int, params: array}>}>
     */
    private function buildTrendWidgets(array $stats): array
    {
        /** @var array<string, int> $newRegistrations */
        $newRegistrations = $stats['newRegistrations'];
        /** @var array<string, int> $activeSessions */
        $activeSessions = $stats['activeSessions'];
        /** @var int $lifespanDays */
        $lifespanDays = $stats['rememberLifespanDays'];

        return [
            $this->trendWidget('voyti.view.dashboard.new_registrations', $newRegistrations, $lifespanDays),
            $this->trendWidget('voyti.view.dashboard.active_sessions', $activeSessions, $lifespanDays),
        ];
    }

    /**
     * @return array{labelKey: string, value: mixed, url: string, borderClass: string}
     */
    private function tile(string $labelKey, mixed $value, string $route, string $borderClass): array
    {
        return [
            'labelKey' => $labelKey,
            'value' => $value,
            'url' => $this->url->generate($route),
            'borderClass' => $borderClass,
        ];
    }

    /**
     * @param array<string, int> $data
     * @return array{titleKey: string, periods: list<array{labelKey: string, value: int, params: array}>}
     */
    private function trendWidget(string $titleKey, array $data, int $lifespanDays): array
    {
        return [
            'titleKey' => $titleKey,
            'periods' => [
                ['labelKey' => 'voyti.view.dashboard.last_1d', 'value' => $data['oneDay'], 'params' => []],
                ['labelKey' => 'voyti.view.dashboard.last_7d', 'value' => $data['sevenDays'], 'params' => []],
                ['labelKey' => 'voyti.view.dashboard.last_lifespan', 'value' => $data['lifespan'], 'params' => ['days' => $lifespanDays]],
            ],
        ];
    }
}
