<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Privacy;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Privacy\AnonymizeViewData;

final class AnonymizeViewDataTest extends TestCase
{
    public function testCreateAssignsAnonymizeUrl(): void
    {
        $data = AnonymizeViewData::create(new FakeUrlGenerator());

        self::assertSame('//voyti/user-privacy-anonymize', $data->formSubmitUrl);
    }
}
