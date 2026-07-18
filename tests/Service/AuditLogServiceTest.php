<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Service;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\UserAuditLog;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\AuditLogService;
use YiiRocks\Voyti\tests\Support\DatabaseSetupTrait;

final class AuditLogServiceTest extends TestCase
{
    use DatabaseSetupTrait;

    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }

    public function testLogDoesNothingWhenDisabled(): void
    {
        $service = new AuditLogService(new ModuleConfig(enableAuditLog: false));

        $service->log(1, 'user.create');

        self::assertCount(0, UserAuditLog::search()->all());
    }

    public function testLogPersistsMinimalEntry(): void
    {
        $service = new AuditLogService(new ModuleConfig(enableAuditLog: true));

        $service->log(1, 'user.create');

        $logs = UserAuditLog::search()->all();
        self::assertCount(1, $logs);
        self::assertSame(1, $logs[0]->getActorUserId());
        self::assertSame('user.create', $logs[0]->getAction());
        self::assertNull($logs[0]->getTargetUserId());
        self::assertNull($logs[0]->getTargetName());
        self::assertNull($logs[0]->getContext());
        self::assertGreaterThan(0, $logs[0]->getCreatedAt());
    }

    public function testLogPersistsNullActorForSystemActions(): void
    {
        $service = new AuditLogService(new ModuleConfig(enableAuditLog: true));

        $service->log(null, 'user.create', targetUserId: 5);

        $logs = UserAuditLog::search()->all();
        self::assertCount(1, $logs);
        self::assertNull($logs[0]->getActorUserId());
        self::assertSame(5, $logs[0]->getTargetUserId());
    }

    public function testLogPersistsTargetAndContext(): void
    {
        $service = new AuditLogService(new ModuleConfig(enableAuditLog: true));

        $service->log(1, 'rbac.role.update', targetUserId: null, targetName: 'editor', context: ['previousName' => 'old-editor']);

        $logs = UserAuditLog::search()->all();
        self::assertCount(1, $logs);
        self::assertSame('editor', $logs[0]->getTargetName());
        self::assertSame('{"previousName":"old-editor"}', $logs[0]->getContext());
    }
}
