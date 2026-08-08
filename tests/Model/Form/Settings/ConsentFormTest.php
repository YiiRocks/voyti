<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Settings;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\Form\Settings\ConsentForm;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;

#[AllowMockObjectsWithoutExpectations]
final class ConsentFormTest extends TestCase
{
    use TranslatorMockTrait;

    public function testGetPropertyLabels(): void
    {
        $form = new ConsentForm($this->createTranslator(), 'test', 'voyti.view.anonymize.confirm_label');
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('consent', $labels);
    }

    public function testGetValidationPropertyLabels(): void
    {
        $form = new ConsentForm($this->createTranslator(), 'test', 'voyti.view.anonymize.confirm_label');
        $this->assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
    }
}
