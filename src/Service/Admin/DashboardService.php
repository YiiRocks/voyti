<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Admin;

use YiiRocks\Voyti\Helper\AuthHelper;
use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\AuditLog;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Rbac\ItemsStorageInterface;
use Yiisoft\Translator\TranslatorInterface;

final readonly class DashboardService
{
    private const int RECENT_AUDIT_LOG_LIMIT = 5;

    public function __construct(
        private AuthHelper $authHelper,
        private ModuleConfig $config,
        private ItemsStorageInterface $itemsStorage,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{
     *     userTotal: int,
     *     userBlocked: int,
     *     userUnconfirmed: int|null,
     *     roleCount: int,
     *     permissionCount: int,
     *     ruleCount: int,
     *     recentAuditLogs: list<array{createdAt: string, action: string, targetLabel: string}>,
     * }
     */
    public function getStats(): array
    {
        return [
            /** @infection-ignore-all Query::count() is typed int|string for driver portability; sqlite already returns int here, so the cast is unobservable in tests but keeps the return type sound on drivers that return numeric strings. */
            'userTotal' => (int) User::query()->count(),
            /** @infection-ignore-all Same driver-portability cast as userTotal above. */
            'userBlocked' => (int) User::searchQuery(['status' => 'blocked'])->count(),
            'userUnconfirmed' => $this->unconfirmedUserCount(),
            'roleCount' => count($this->itemsStorage->getRoles()),
            'permissionCount' => count($this->itemsStorage->getPermissions()),
            'ruleCount' => count($this->authHelper->getRuleNames()),
            'recentAuditLogs' => $this->recentAuditLogs(),
        ];
    }

    /**
     * @return list<array{createdAt: string, action: string, targetLabel: string}>
     */
    private function recentAuditLogs(): array
    {
        /** @var list<AuditLog> $logs */
        $logs = AuditLog::search()->limit(self::RECENT_AUDIT_LOG_LIMIT)->all();

        return array_map(
            fn (AuditLog $log): array => [
                'createdAt' => TimezoneHelper::formatLocalized($log->getCreatedAt(), $this->translator->getLocale()),
                'action' => $log->getAction(),
                'targetLabel' => $this->targetLabel($log),
            ],
            $logs,
        );
    }

    private function targetLabel(AuditLog $log): string
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
