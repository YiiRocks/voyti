<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Privacy;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Privacy\DeleteViewData;

final class DeleteViewDataTest extends TestCase
{
    public function testCreateAssignsDeleteUrl(): void
    {
        $data = DeleteViewData::create(new FakeUrlGenerator());

        self::assertSame('//voyti/user-privacy-delete', $data->formSubmitUrl);
    }
}
