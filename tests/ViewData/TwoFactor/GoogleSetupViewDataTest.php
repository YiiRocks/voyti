<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\TwoFactor;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\TwoFactor\GoogleSetupViewData;
use Yiisoft\Translator\Translator;

final class GoogleSetupViewDataTest extends TestCase
{
    public function testCreateAssignsQrCodeAndSecret(): void
    {
        $translator = new Translator('en', null, 'voyti');

        $data = GoogleSetupViewData::create('<svg></svg>', 'ABC123', new FakeUrlGenerator(), $translator);

        self::assertSame('<svg></svg>', $data->qrCodeUri);
        self::assertSame('ABC123', $data->secret);
        self::assertSame('//voyti/two-factor-enable', $data->formSubmitUrl);
        self::assertSame('voyti.view.two_factor.renew', $data->renewLabel);
        self::assertSame('voyti.view.two_factor.manual_entry', $data->manualEntryLabel);
    }

    public function testCreateWithNullSecret(): void
    {
        $translator = new Translator('en', null, 'voyti');

        $data = GoogleSetupViewData::create('', null, new FakeUrlGenerator(), $translator);

        self::assertNull($data->secret);
    }
}
