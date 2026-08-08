<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin;

use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Admin\AuditLog\IndexViewData as AuditLogIndexViewData;
use YiiRocks\Voyti\ViewData\Admin\Dashboard\IndexViewData as DashboardIndexViewData;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Translator\Translator;

final class AdminViewDataTest extends TestCase
{
    use TranslatorMockTrait;

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

    public function testAuditLogCreateDefaultsMissingFiltersToEmptyString(): void
    {
        $paginator = new OffsetPaginator(new IterableDataReader([]));
        $translator = new Translator('en', null, 'voyti');

        $data = AuditLogIndexViewData::create([], $paginator, [], new FakeUrlGenerator(), $translator);

        self::assertSame(['actorUserId' => '', 'targetUserId' => '', 'action' => ''], $data->filters);
    }

    public function testAuditLogCreateNormalizesFiltersAndBuildsPaginationUrls(): void
    {
        $paginator = new OffsetPaginator(new IterableDataReader([]));
        $translator = new Translator('en', null, 'voyti');

        $data = AuditLogIndexViewData::create(
            [],
            $paginator,
            ['actor_user_id' => '1', 'target_user_id' => '2', 'action' => 'login'],
            new FakeUrlGenerator(),
            $translator,
        );

        self::assertSame(['actorUserId' => '1', 'targetUserId' => '2', 'action' => 'login'], $data->filters);
        self::assertSame('//voyti/admin-audit-log', $data->filterActionUrl);
        self::assertStringContainsString('YII-DATAVIEW-PAGE-PLACEHOLDER', $data->pageUrlPattern);
        self::assertStringContainsString('page=1', $data->firstPageUrl);
        self::assertSame($paginator, $data->paginator);
        self::assertNotEmpty($data->menu->items);
    }

    public function testDashboardCreateBuildsTrendPeriodsWithLifespanParam(): void
    {
        $data = DashboardIndexViewData::create(self::BASE_STATS, new FakeUrlGenerator(), $this->createTranslator());

        self::assertCount(2, $data->trendWidgets);
        $lifespanPeriod = $data->trendWidgets[0]->periods[2];
        self::assertSame(3, $lifespanPeriod->value);
        self::assertSame(['days' => 30], $lifespanPeriod->params);
    }

    public function testDashboardCreateWithoutUnconfirmedUsersOmitsTile(): void
    {
        $stats = self::BASE_STATS;
        $stats['userUnconfirmed'] = null;

        $data = DashboardIndexViewData::create($stats, new FakeUrlGenerator(), $this->createTranslator());

        self::assertCount(5, $data->tiles);
    }
}
