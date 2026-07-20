<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Admin;

use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Aggregates the stats shown on the admin dashboard: user/role/permission/rule counts,
 * registration and active-session trends, and recent audit log entries.
 */
final readonly class DashboardService
{
    private const int RECENT_AUDIT_LOG_LIMIT = 5;
    private const int SECONDS_PER_DAY = 86400;

    public function __construct(
        private AuthHelper $authHelper,
        private ModuleConfig $config,
        private ItemsStorageInterface $itemsStorage,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @return array{
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
     * }
     */
    public function getStats(?string $viewerTimezone = null): array
    {
        $now = time();

        return [
            /**
             * @infection-ignore-all Query::count() is typed int|string for driver portability; sqlite
             * already returns int here, so the cast is unobservable in tests but keeps the return
             * type sound on drivers that return numeric strings.
             */
            'userTotal' => (int) User::query()->count(),
            /** @infection-ignore-all Same driver-portability cast as userTotal above. */
            'userBlocked' => (int) User::searchQuery(['status' => 'blocked'])->count(),
            'userUnconfirmed' => $this->unconfirmedUserCount(),
            'roleCount' => count($this->itemsStorage->getRoles()),
            'permissionCount' => count($this->itemsStorage->getPermissions()),
            'ruleCount' => count($this->authHelper->getRuleNames()),
            'newRegistrations' => $this->newRegistrationsTrend($now),
            'activeSessions' => $this->activeSessionsTrend($now),
            'rememberLifespanDays' => (int) round($this->config->rememberLoginLifespan / self::SECONDS_PER_DAY),
            'recentAuditLogs' => $this->recentAuditLogs($viewerTimezone),
        ];
    }

    /**
     * Counts sessions with activity (`updated_at`) inside each window, regardless of whether they
     * have since been revoked - this is a usage trend ("how many sessions were active in this
     * period"), not a live count of currently-unrevoked sessions.
     *
     * @return array{oneDay: int, sevenDays: int, lifespan: int}
     */
    private function activeSessionsTrend(int $now): array
    {
        return [
            /** @infection-ignore-all Same driver-portability cast as userTotal in getStats() above. */
            'oneDay' => (int) UserSessions::query()
                ->andWhere(['>=', 'updated_at', $now - self::SECONDS_PER_DAY])
                ->count(),
            /** @infection-ignore-all Same driver-portability cast as userTotal in getStats() above. */
            'sevenDays' => (int) UserSessions::query()
                ->andWhere(['>=', 'updated_at', $now - (self::SECONDS_PER_DAY * 7)])
                ->count(),
            /** @infection-ignore-all Same driver-portability cast as userTotal in getStats() above. */
            'lifespan' => (int) UserSessions::query()
                ->andWhere(['>=', 'updated_at', $now - $this->config->rememberLoginLifespan])
                ->count(),
        ];
    }

    /**
     * @return array{oneDay: int, sevenDays: int, lifespan: int}
     */
    private function newRegistrationsTrend(int $now): array
    {
        return [
            /** @infection-ignore-all Same driver-portability cast as userTotal in getStats() above. */
            'oneDay' => (int) User::query()
                ->andWhere(['>=', 'created_at', $now - self::SECONDS_PER_DAY])
                ->count(),
            /** @infection-ignore-all Same driver-portability cast as userTotal in getStats() above. */
            'sevenDays' => (int) User::query()
                ->andWhere(['>=', 'created_at', $now - (self::SECONDS_PER_DAY * 7)])
                ->count(),
            /** @infection-ignore-all Same driver-portability cast as userTotal in getStats() above. */
            'lifespan' => (int) User::query()
                ->andWhere(['>=', 'created_at', $now - $this->config->rememberLoginLifespan])
                ->count(),
        ];
    }

    /**
     * @return list<array{createdAt: string, action: string, targetLabel: string}>
     */
    private function recentAuditLogs(?string $viewerTimezone): array
    {
        /** @var list<UserAuditLog> $logs */
        $logs = UserAuditLog::search()->limit(self::RECENT_AUDIT_LOG_LIMIT)->all();

        return array_map(
            fn(UserAuditLog $log): array => [
                'createdAt' => TimezoneHelper::formatLocalized(
                    $log->getCreatedAt(),
                    $this->translator->getLocale(),
                    $viewerTimezone,
                ),
                'action' => $log->getAction(),
                'targetLabel' => $this->targetLabel($log),
            ],
            $logs,
        );
    }

    private function targetLabel(UserAuditLog $log): string
    {
        $name = $log->getTargetName() ?? '';
        $userId = $log->getTargetUserId();

        return $userId !== null ? $name . ' (#' . $userId . ')' : $name;
    }

    private function unconfirmedUserCount(): ?int
    {
        if (!$this->config->enableEmailConfirmation) {
            return null;
        }

        /** @infection-ignore-all Same driver-portability cast as userTotal in getStats() above. */
        return (int) User::searchQuery(['status' => 'unconfirmed'])->count();
    }
}
