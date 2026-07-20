<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\TwoFactor;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\TwoFactor\BackupCodesViewData;
use Yiisoft\Translator\Translator;

final class BackupCodesViewDataTest extends TestCase
{
    public function testCreateAssignsCodesAndContinueUrl(): void
    {
        $translator = new Translator('en', null, 'voyti');

        $data = BackupCodesViewData::create(['aaa', 'bbb'], new ModuleConfig(), new FakeUrlGenerator(), $translator);

        self::assertSame(['aaa', 'bbb'], $data->codes);
        self::assertSame('//voyti/two-factor', $data->continueUrl);
        self::assertNotEmpty($data->menu->items);
    }
}
