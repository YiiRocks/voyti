<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Privacy;

use YiiRocks\Voyti\Model\Form\Settings\GdprConsentForm;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Privacy\AnonymizeViewData;
use YiiRocks\Voyti\ViewData\Privacy\DeleteViewData;
use YiiRocks\Voyti\ViewData\Privacy\GdprConsentViewData;
use YiiRocks\Voyti\ViewData\Privacy\IndexViewData;

final class PrivacyViewDataTest extends TestCase
{
    use TranslatorMockTrait;

    public function testAnonymizeCreateAssignsAnonymizeUrl(): void
    {
        $config = VoytiConfigFactory::create();

        $data = AnonymizeViewData::create($config, new FakeUrlGenerator(), $this->createTranslator());

        self::assertSame('//voyti/user-privacy-anonymize', $data->formSubmitUrl);
        self::assertNotEmpty($data->menu->items);
    }

    public function testDeleteCreateAssignsDeleteUrl(): void
    {
        $data = DeleteViewData::create(new FakeUrlGenerator());

        self::assertSame('//voyti/user-privacy-delete', $data->formSubmitUrl);
    }

    public function testGdprConsentCreateWhenLockedWithConsentDate(): void
    {
        $form = new GdprConsentForm($this->createTranslator());
        $form->consent = true;
        $form->consentDate = 1700000000;
        $form->timezone = 'UTC';
        $config = VoytiConfigFactory::create();

        $data = GdprConsentViewData::create($form, $config, new FakeUrlGenerator(), $this->createTranslator());

        self::assertTrue($data->isLocked);
        self::assertNotNull($data->consentDateDisplay);
        self::assertNotEmpty($data->menu->items);
    }

    public function testGdprConsentCreateWhenLockedWithoutConsentDate(): void
    {
        $form = new GdprConsentForm($this->createTranslator());
        $form->consent = true;
        $form->consentDate = null;
        $config = VoytiConfigFactory::create();

        $data = GdprConsentViewData::create($form, $config, new FakeUrlGenerator(), $this->createTranslator());

        self::assertTrue($data->isLocked);
        self::assertNull($data->consentDateDisplay);
        self::assertNotEmpty($data->menu->items);
    }

    public function testIndexCreateWithDeleteEnabledGdprDisabled(): void
    {
        $config = VoytiConfigFactory::create(enableGdprCompliance: false, allowAccountDelete: true);

        $data = IndexViewData::create($config, new FakeUrlGenerator(), $this->createTranslator());

        self::assertFalse($data->showGdprLinks);
        self::assertTrue($data->showDeleteLink);
        self::assertSame('', $data->gdprConsentUrl);
        self::assertSame('', $data->exportUrl);
        self::assertSame('', $data->anonymizeUrl);
        self::assertSame('//voyti/user-privacy-delete', $data->deleteUrl);
    }
}
