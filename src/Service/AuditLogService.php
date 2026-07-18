<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Json\Json;

/**
 * Records an admin/system action as a {@see UserAuditLog} row, a no-op when
 * `ModuleConfig::$enableAuditLog` is disabled.
 */
final readonly class AuditLogService
{
    public function __construct(
        private ModuleConfig $config,
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public function log(
        ?int $actorUserId,
        string $action,
        ?int $targetUserId = null,
        ?string $targetName = null,
        array $context = [],
    ): void {
        if (!$this->config->enableAuditLog) {
            return;
        }

        $log = new UserAuditLog();
        $log->setActorUserId($actorUserId);
        $log->setAction($action);
        $log->setTargetUserId($targetUserId);
        $log->setTargetName($targetName);
        $log->setContext($context === [] ? null : Json::encode($context));
        $log->setCreatedAt(time());
        $log->save();
    }
}
