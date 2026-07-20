<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Privacy;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\ViewData\Privacy\IndexViewData;
use Yiisoft\Translator\Translator;
use Yiisoft\Translator\TranslatorInterface;

final class IndexViewDataTest extends TestCase
{
    public function testCreateWithGdprAndDeleteDisabled(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: false, allowAccountDelete: false);

        $data = IndexViewData::create($config, new FakeUrlGenerator(), $this->translator());

        self::assertFalse($data->showGdprLinks);
        self::assertFalse($data->showDeleteLink);
    }

    public function testCreateWithGdprAndDeleteEnabled(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true, allowAccountDelete: true);

        $data = IndexViewData::create($config, new FakeUrlGenerator(), $this->translator());

        self::assertTrue($data->showGdprLinks);
        self::assertTrue($data->showDeleteLink);
        self::assertSame('//voyti/privacy-gdpr-consent', $data->gdprConsentUrl);
        self::assertSame('//voyti/privacy-export', $data->exportUrl);
        self::assertSame('//voyti/privacy-anonymize', $data->anonymizeUrl);
        self::assertSame('//voyti/privacy-delete', $data->deleteUrl);
        self::assertNotEmpty($data->menu->items);
    }

    private function translator(): TranslatorInterface
    {
        return new Translator('en', null, 'voyti');
    }
}
