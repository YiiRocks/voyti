<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Privacy;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\ViewData\Privacy\IndexViewData;

final class IndexViewDataTest extends TestCase
{
    use TranslatorMockTrait;

    public function testCreateWithDeleteEnabledGdprDisabled(): void
    {
        $config = VoytiConfigFactory::create(enableGdprCompliance: false, allowAccountDelete: true);

        $data = IndexViewData::create($config, new FakeUrlGenerator(), $this->createTranslator(), false, null);

        self::assertFalse($data->showGdprLinks);
        self::assertTrue($data->showDeleteLink);
        self::assertSame('', $data->gdprConsentUrl);
        self::assertSame('', $data->exportUrl);
        self::assertSame('', $data->anonymizeUrl);
        self::assertSame('//voyti/user-privacy-delete', $data->deleteUrl);
    }

    public function testCreateWithGdprAndDeleteDisabled(): void
    {
        $config = VoytiConfigFactory::create(enableGdprCompliance: false, allowAccountDelete: false);

        $data = IndexViewData::create($config, new FakeUrlGenerator(), $this->createTranslator(), false, null);

        self::assertFalse($data->showGdprLinks);
        self::assertFalse($data->showDeleteLink);
    }

    public function testCreateWithGdprAndDeleteEnabled(): void
    {
        $config = VoytiConfigFactory::create(enableGdprCompliance: true, allowAccountDelete: true);

        $data = IndexViewData::create($config, new FakeUrlGenerator(), $this->createTranslator(), false, null);

        self::assertTrue($data->showGdprLinks);
        self::assertTrue($data->showDeleteLink);
        self::assertSame('//voyti/user-privacy-gdpr-consent', $data->gdprConsentUrl);
        self::assertSame('//voyti/user-privacy-export', $data->exportUrl);
        self::assertSame('//voyti/user-privacy-anonymize', $data->anonymizeUrl);
        self::assertSame('//voyti/user-privacy-delete', $data->deleteUrl);
        self::assertNotEmpty($data->menu->items);
    }

    public function testCreateWithGdprEnabledDeleteDisabled(): void
    {
        $config = VoytiConfigFactory::create(enableGdprCompliance: true, allowAccountDelete: false);

        $data = IndexViewData::create($config, new FakeUrlGenerator(), $this->createTranslator(), false, null);

        self::assertTrue($data->showGdprLinks);
        self::assertFalse($data->showDeleteLink);
        self::assertSame('', $data->deleteUrl);
    }
}
