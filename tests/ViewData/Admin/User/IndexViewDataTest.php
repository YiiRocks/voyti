<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\User;

use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Admin\User\IndexViewData;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Translator\Translator;

final class IndexViewDataTest extends TestCase
{
    use UserFactoryTrait;

    public function testCreateWithoutSwitchedIdentity(): void
    {
        $user = $this->buildUser('jane');
        $paginator = new OffsetPaginator(new IterableDataReader([]));
        $translator = new Translator('en', null, 'voyti');

        $data = IndexViewData::create(
            [$user],
            $paginator,
            ['username' => 'jane'],
            ModuleConfigFactory::create(),
            new FakeUrlGenerator(),
            $translator,
            false,
            null,
            999999,
        );

        self::assertCount(1, $data->users);
        self::assertSame('jane', $data->users[0]->username);
        self::assertNull($data->switchedBannerMessage);
        self::assertSame(['username' => 'jane', 'email' => '', 'status' => ''], $data->filters);
        self::assertSame('//voyti/admin-users-create', $data->createUserUrl);
    }

    public function testCreateWithSwitchedIdentity(): void
    {
        $originalUser = $this->buildUser('admin');
        $paginator = new OffsetPaginator(new IterableDataReader([]));
        $translator = $this->createTranslator();

        $data = IndexViewData::create(
            [],
            $paginator,
            [],
            ModuleConfigFactory::create(),
            new FakeUrlGenerator(),
            $translator,
            true,
            $originalUser,
            999999,
        );

        self::assertNotNull($data->switchedBannerMessage);
        self::assertStringContainsString('admin', $data->switchedBannerMessage);
    }
}
