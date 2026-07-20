<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Admin\Rbac\Rule;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Admin\Rbac\Rule\CreateViewData;
use Yiisoft\Translator\Translator;

final class CreateViewDataTest extends TestCase
{
    public function testCreateCarriesErrorsAndUrl(): void
    {
        $translator = new Translator('en', null, 'voyti');

        $data = CreateViewData::create(['class' => ['invalid']], new FakeUrlGenerator(), $translator);

        self::assertSame('//voyti/admin-rbac-rules-create', $data->formSubmitUrl);
        self::assertSame(['class' => ['invalid']], $data->errors);
        self::assertNotEmpty($data->menu->items);
    }
}
