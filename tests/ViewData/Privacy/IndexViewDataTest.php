<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Privacy;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use YiiRocks\Voyti\ViewData\Privacy\IndexViewData;

final class IndexViewDataTest extends TestCase
{
    use TranslatorMockTrait;

    public function testCreateWithDeleteEnabledGdprDisabled(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: false, allowAccountDelete: true);

        $data = IndexViewData::create($config, new FakeUrlGenerator(), $this->createTranslator());

        self::assertFalse($data->showGdprLinks);
        self::assertTrue($data->showDeleteLink);
        self::assertSame('', $data->gdprConsentUrl);
        self::assertSame('', $data->exportUrl);
        self::assertSame('', $data->anonymizeUrl);
        self::assertSame('//voyti/privacy-delete', $data->deleteUrl);
    }

    public function testCreateWithGdprAndDeleteDisabled(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: false, allowAccountDelete: false);

        $data = IndexViewData::create($config, new FakeUrlGenerator(), $this->createTranslator());

        self::assertFalse($data->showGdprLinks);
        self::assertFalse($data->showDeleteLink);
    }

    public function testCreateWithGdprAndDeleteEnabled(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true, allowAccountDelete: true);

        $data = IndexViewData::create($config, new FakeUrlGenerator(), $this->createTranslator());

        self::assertTrue($data->showGdprLinks);
        self::assertTrue($data->showDeleteLink);
        self::assertSame('//voyti/privacy-gdpr-consent', $data->gdprConsentUrl);
        self::assertSame('//voyti/privacy-export', $data->exportUrl);
        self::assertSame('//voyti/privacy-anonymize', $data->anonymizeUrl);
        self::assertSame('//voyti/privacy-delete', $data->deleteUrl);
        self::assertNotEmpty($data->menu->items);
    }

    public function testCreateWithGdprEnabledDeleteDisabled(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true, allowAccountDelete: false);

        $data = IndexViewData::create($config, new FakeUrlGenerator(), $this->createTranslator());

        self::assertTrue($data->showGdprLinks);
        self::assertFalse($data->showDeleteLink);
        self::assertSame('', $data->deleteUrl);
    }
}
