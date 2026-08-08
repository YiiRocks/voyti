<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Settings;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\Form\Settings\GdprConsentForm;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;

#[AllowMockObjectsWithoutExpectations]
final class GdprConsentFormTest extends TestCase
{
    use TranslatorMockTrait;

    public function testGetPropertyLabels(): void
    {
        $form = new GdprConsentForm($this->createTranslator());
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('consent', $labels);
    }

    public function testGetValidationPropertyLabels(): void
    {
        $form = new GdprConsentForm($this->createTranslator());
        $this->assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
    }
}
