<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\Dashboard;

use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `admin/dashboard/index` screen.
 */
final readonly class IndexViewData
{
    /**
     * @param list<DashboardTile> $tiles
     * @param list<TrendWidget> $trendWidgets
     * @param list<array{createdAt: string, action: string, targetLabel: string}> $recentAuditLogs
     */
    private function __construct(
        public MenuViewData $menu,
        public array $tiles,
        public array $trendWidgets,
        public array $recentAuditLogs,
        public string $auditLogUrl,
    ) {}

    /**
     * @param array{
     *     userTotal: int,
     *     userBlocked: int,
     *     userUnconfirmed: int|null,
     *     roleCount: int,
     *     permissionCount: int,
     *     ruleCount: int,
     *     newRegistrations: array{oneDay: int, sevenDays: int, lifespan: int},
     *     activeSessions: array{oneDay: int, sevenDays: int, lifespan: int},
     *     rememberLifespanDays: int,
     *     recentAuditLogs: list<array{createdAt: string, action: string, targetLabel: string}>,
     * } $stats
     */
    public static function create(array $stats, UrlGeneratorInterface $url, TranslatorInterface $translator): self
    {
        $tiles = [
            new DashboardTile('voyti.view.dashboard.users_total', $stats['userTotal'], $url->generate('voyti/admin-users'), 'border-primary'),
            new DashboardTile('voyti.view.dashboard.users_blocked', $stats['userBlocked'], $url->generate('voyti/admin-users'), 'border-danger'),
        ];
        if ($stats['userUnconfirmed'] !== null) {
            $tiles[] = new DashboardTile('voyti.view.dashboard.users_unconfirmed', $stats['userUnconfirmed'], $url->generate('voyti/admin-users'), 'border-warning');
        }
        $tiles[] = new DashboardTile('voyti.view.dashboard.roles', $stats['roleCount'], $url->generate('voyti/admin-rbac-roles'), 'border-secondary');
        $tiles[] = new DashboardTile('voyti.view.dashboard.permissions', $stats['permissionCount'], $url->generate('voyti/admin-rbac-permissions'), 'border-secondary');
        $tiles[] = new DashboardTile('voyti.view.dashboard.rules', $stats['ruleCount'], $url->generate('voyti/admin-rbac-rules'), 'border-secondary');

        $trendWidgets = [
            new TrendWidget('voyti.view.dashboard.new_registrations', self::periods($stats['newRegistrations'], $stats['rememberLifespanDays'])),
            new TrendWidget('voyti.view.dashboard.active_sessions', self::periods($stats['activeSessions'], $stats['rememberLifespanDays'])),
        ];

        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            tiles: $tiles,
            trendWidgets: $trendWidgets,
            recentAuditLogs: $stats['recentAuditLogs'],
            auditLogUrl: $url->generate('voyti/admin-audit-log'),
        );
    }

    /**
     * @param array{oneDay: int, sevenDays: int, lifespan: int} $trend
     *
     * @return list<TrendPeriod>
     */
    private static function periods(array $trend, int $rememberLifespanDays): array
    {
        return [
            new TrendPeriod('voyti.view.dashboard.last_1d', $trend['oneDay']),
            new TrendPeriod('voyti.view.dashboard.last_7d', $trend['sevenDays']),
            new TrendPeriod('voyti.view.dashboard.last_lifespan', $trend['lifespan'], ['days' => $rememberLifespanDays]),
        ];
    }
}
