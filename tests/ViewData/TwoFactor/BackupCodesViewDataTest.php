<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\TwoFactor;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\ViewData\TwoFactor\BackupCodesViewData;
use Yiisoft\Translator\Translator;

final class BackupCodesViewDataTest extends TestCase
{
    public function testCreateAssignsCodesAndContinueUrl(): void
    {
        $translator = new Translator('en', null, 'voyti');

        $data = BackupCodesViewData::create(['aaa', 'bbb'], ModuleConfigFactory::create(), new FakeUrlGenerator(), $translator);

        self::assertSame(['aaa', 'bbb'], $data->codes);
        self::assertSame('//voyti/user-two-factor', $data->continueUrl);
        self::assertNotEmpty($data->menu->items);
    }
}
