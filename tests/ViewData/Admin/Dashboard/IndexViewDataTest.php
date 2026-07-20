<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\Dashboard;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Admin\Dashboard\IndexViewData;
use Yiisoft\Translator\Translator;
use Yiisoft\Translator\TranslatorInterface;

final class IndexViewDataTest extends TestCase
{
    private const array BASE_STATS = [
        'userTotal' => 10,
        'userBlocked' => 2,
        'userUnconfirmed' => 1,
        'roleCount' => 3,
        'permissionCount' => 4,
        'ruleCount' => 1,
        'newRegistrations' => ['oneDay' => 1, 'sevenDays' => 2, 'lifespan' => 3],
        'activeSessions' => ['oneDay' => 4, 'sevenDays' => 5, 'lifespan' => 6],
        'rememberLifespanDays' => 30,
        'recentAuditLogs' => [['createdAt' => 'now', 'action' => 'login', 'targetLabel' => 'user#1']],
    ];

    public function testCreateBuildsTrendPeriodsWithLifespanParam(): void
    {
        $data = IndexViewData::create(self::BASE_STATS, new FakeUrlGenerator(), $this->translator());

        self::assertCount(2, $data->trendWidgets);
        $lifespanPeriod = $data->trendWidgets[0]->periods[2];
        self::assertSame(3, $lifespanPeriod->value);
        self::assertSame(['days' => 30], $lifespanPeriod->params);
    }

    public function testCreateWithoutUnconfirmedUsersOmitsTile(): void
    {
        $stats = self::BASE_STATS;
        $stats['userUnconfirmed'] = null;

        $data = IndexViewData::create($stats, new FakeUrlGenerator(), $this->translator());

        self::assertCount(5, $data->tiles);
    }

    public function testCreateWithUnconfirmedUsersAddsExtraTile(): void
    {
        $data = IndexViewData::create(self::BASE_STATS, new FakeUrlGenerator(), $this->translator());

        self::assertCount(6, $data->tiles);
        self::assertSame('voyti.view.dashboard.users_unconfirmed', $data->tiles[2]->labelKey);
        self::assertSame(1, $data->tiles[2]->value);
        self::assertSame($data->recentAuditLogs, self::BASE_STATS['recentAuditLogs']);
        self::assertSame('//voyti/admin-audit-log', $data->auditLogUrl);
        self::assertNotEmpty($data->menu->items);
    }

    private function translator(): TranslatorInterface
    {
        return new Translator('en', null, 'voyti');
    }
}
