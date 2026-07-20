<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Session;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Session\ConfirmViewData;

final class ConfirmViewDataTest extends TestCase
{
    public function testCreateAssignsMethodAndConfirmUrl(): void
    {
        $data = ConfirmViewData::create('email', new FakeUrlGenerator());

        self::assertSame('email', $data->method);
        self::assertSame('//voyti/session-confirm', $data->formSubmitUrl);
    }
}
