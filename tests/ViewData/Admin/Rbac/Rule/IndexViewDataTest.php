<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\Rbac\Rule;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Admin\Rbac\Rule\IndexViewData;
use Yiisoft\Translator\Translator;

final class IndexViewDataTest extends TestCase
{
    public function testCreateBuildsRowsWithUrls(): void
    {
        $translator = new Translator('en', null, 'voyti');

        $data = IndexViewData::create(['App\\Rule\\MyRule'], new FakeUrlGenerator(), $translator);

        self::assertSame('//voyti/admin-rbac-rules-create', $data->createUrl);
        self::assertCount(1, $data->rules);
        self::assertSame('App\\Rule\\MyRule', $data->rules[0]->name);
        self::assertSame('//voyti/admin-rbac-rules-update?name=App%5CRule%5CMyRule', $data->rules[0]->updateUrl);
        self::assertSame('//voyti/admin-rbac-rules-delete?name=App%5CRule%5CMyRule', $data->rules[0]->formSubmitUrl);
        self::assertNotEmpty($data->menu->items);
    }
}
