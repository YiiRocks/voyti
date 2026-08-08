<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Admin;

use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\Model\UserSessions;
use YiiRocks\Voyti\VoytiConfig;
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
        private VoytiConfig $config,
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
            'userTotal' => $this->toInt(User::query()->count()),
            'userBlocked' => $this->toInt(User::searchQuery(['status' => 'blocked'])->count()),
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
            'oneDay' => $this->toInt(
                UserSessions::query()->andWhere(['>=', 'updated_at', $now - self::SECONDS_PER_DAY])->count(),
            ),
            'sevenDays' => $this->toInt(
                UserSessions::query()->andWhere(['>=', 'updated_at', $now - (self::SECONDS_PER_DAY * 7)])->count(),
            ),
            'lifespan' => $this->toInt(
                UserSessions::query()->andWhere(['>=', 'updated_at', $now - $this->config->rememberLoginLifespan])->count(),
            ),
        ];
    }

    /**
     * @return array{oneDay: int, sevenDays: int, lifespan: int}
     */
    private function newRegistrationsTrend(int $now): array
    {
        return [
            'oneDay' => $this->toInt(
                User::query()->andWhere(['>=', 'created_at', $now - self::SECONDS_PER_DAY])->count(),
            ),
            'sevenDays' => $this->toInt(
                User::query()->andWhere(['>=', 'created_at', $now - (self::SECONDS_PER_DAY * 7)])->count(),
            ),
            'lifespan' => $this->toInt(
                User::query()->andWhere(['>=', 'created_at', $now - $this->config->rememberLoginLifespan])->count(),
            ),
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

    /**
     * Narrows a `Query::count()` result to int. That method is typed `int|string` for driver
     * portability (sqlite returns int, some drivers return numeric strings).
     */
    private function toInt(int|string $count): int
    {
        /** @infection-ignore-all The cast is unobservable under sqlite (already int) but keeps the count sound on drivers that return numeric strings. */
        return (int) $count;
    }

    private function unconfirmedUserCount(): ?int
    {
        if (!$this->config->enableEmailConfirmation) {
            return null;
        }

        return $this->toInt(User::searchQuery(['status' => 'unconfirmed'])->count());
    }
}
