<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\AuditLog;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Admin\AuditLog\IndexViewData;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Translator\Translator;

final class IndexViewDataTest extends TestCase
{
    public function testCreateDefaultsMissingFiltersToEmptyString(): void
    {
        $paginator = new OffsetPaginator(new IterableDataReader([]));
        $translator = new Translator('en', null, 'voyti');

        $data = IndexViewData::create([], $paginator, [], new FakeUrlGenerator(), $translator);

        self::assertSame(['actorUserId' => '', 'targetUserId' => '', 'action' => ''], $data->filters);
    }

    public function testCreateNormalizesFiltersAndBuildsPaginationUrls(): void
    {
        $paginator = new OffsetPaginator(new IterableDataReader([]));
        $translator = new Translator('en', null, 'voyti');

        $data = IndexViewData::create(
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
}
