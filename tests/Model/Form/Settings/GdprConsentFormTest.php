<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Settings;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\Form\Settings\GdprConsentForm;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class GdprConsentFormTest extends TestCase
{
    use TranslatorMockTrait;

    public function testConstruct(): void
    {
        $form = new GdprConsentForm($this->createTranslator());
        $this->assertFalse($form->consent);
        $this->assertNull($form->consentDate);
        $this->assertNull($form->timezone);
    }

    public function testGetAttributeLabels(): void
    {
        $form = new GdprConsentForm($this->createTranslator());
        $labels = $form->getAttributeLabels();
        $this->assertArrayHasKey('consent', $labels);
    }

    public function testGetFormName(): void
    {
        $form = new GdprConsentForm($this->createTranslator());
        $this->assertSame('gdpr-consent', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new GdprConsentForm($this->createTranslator());
        $this->assertSame($form->getAttributeLabels(), $form->getPropertyLabels());
    }

    public function testSetConsent(): void
    {
        $form = new GdprConsentForm($this->createTranslator());
        $form->consent = true;
        $this->assertTrue($form->consent);
    }

    public function testSetConsentDate(): void
    {
        $form = new GdprConsentForm($this->createTranslator());
        $form->consentDate = 1700000000;
        $this->assertSame(1700000000, $form->consentDate);
    }

    public function testSetTimezone(): void
    {
        $form = new GdprConsentForm($this->createTranslator());
        $form->timezone = 'America/New_York';
        $this->assertSame('America/New_York', $form->timezone);
    }
}
