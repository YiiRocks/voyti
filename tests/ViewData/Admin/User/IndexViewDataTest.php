<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\User;

use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Admin\User\IndexViewData;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Translator\Translator;

final class IndexViewDataTest extends TestCase
{
    use UserFactoryTrait;

    public function testCreate(): void
    {
        $user = $this->buildUser('jane');
        $paginator = new OffsetPaginator(new IterableDataReader([]));
        $translator = new Translator('en', null, 'voyti');

        $data = IndexViewData::create(
            [$user],
            $paginator,
            ['username' => 'jane'],
            25,
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            $translator,
            false,
            999999,
        );

        self::assertCount(1, $data->users);
        self::assertSame('jane', $data->users[0]->username);
        self::assertSame(['username' => 'jane', 'email' => '', 'status' => ''], $data->filters);
        self::assertSame('//voyti/admin-users-create', $data->createUserUrl);
        self::assertSame(25, $data->perPage);
        self::assertStringContainsString('perPage=25', $data->pageUrlPattern);
        self::assertStringContainsString('perPage=25', $data->firstPageUrl);
    }

    public function testCreateHidesSwitchIdentityActionWhenAlreadySwitched(): void
    {
        $user = $this->buildUser('jane');
        $paginator = new OffsetPaginator(new IterableDataReader([]));
        $translator = $this->createTranslator();

        $data = IndexViewData::create(
            [$user],
            $paginator,
            [],
            25,
            VoytiConfigFactory::create(),
            new FakeUrlGenerator(),
            $translator,
            true,
            999999,
        );

        self::assertFalse($data->users[0]->showSwitchIdentityAction);
    }
}
