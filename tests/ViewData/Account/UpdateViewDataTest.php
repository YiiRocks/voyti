<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Account;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\ViewData\Account\UpdateViewData;
use Yiisoft\Translator\Translator;

final class UpdateViewDataTest extends TestCase
{
    public function testCreateAssignsUpdateUrlAndMenu(): void
    {
        $translator = new Translator('en', null, 'voyti');

        $data = UpdateViewData::create(ModuleConfigFactory::create(), new FakeUrlGenerator(), $translator);

        self::assertSame('//voyti/user-account', $data->formSubmitUrl);
        self::assertNotEmpty($data->menu->items);
    }
}
