<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\ViewData\Privacy;

use YiiRocks\Voyti\Model\Form\Settings\GdprConsentForm;
use YiiRocks\Voyti\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\tests\TestCase;
use YiiRocks\Voyti\ViewData\Privacy\GdprConsentViewData;

final class GdprConsentViewDataTest extends TestCase
{
    public function testCreateWhenLockedWithConsentDate(): void
    {
        $form = new GdprConsentForm($this->createTranslator());
        $form->consent = true;
        $form->consentDate = 1700000000;
        $form->timezone = 'UTC';

        $data = GdprConsentViewData::create($form, new FakeUrlGenerator(), 'en');

        self::assertTrue($data->isLocked);
        self::assertNotNull($data->consentDateDisplay);
    }

    public function testCreateWhenLockedWithoutConsentDate(): void
    {
        $form = new GdprConsentForm($this->createTranslator());
        $form->consent = true;
        $form->consentDate = null;

        $data = GdprConsentViewData::create($form, new FakeUrlGenerator(), 'en');

        self::assertTrue($data->isLocked);
        self::assertNull($data->consentDateDisplay);
    }

    public function testCreateWhenNotLocked(): void
    {
        $form = new GdprConsentForm($this->createTranslator());
        $form->consent = false;

        $data = GdprConsentViewData::create($form, new FakeUrlGenerator(), 'en');

        self::assertFalse($data->isLocked);
        self::assertNull($data->consentDateDisplay);
        self::assertSame('//voyti/privacy-gdpr-consent', $data->formSubmitUrl);
    }
}
