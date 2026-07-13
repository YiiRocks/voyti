<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Settings;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\Form\Settings\AnonymizeForm;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class AnonymizeFormTest extends TestCase
{
    use TranslatorMockTrait;

    public function testConstruct(): void
    {
        $form = new AnonymizeForm($this->createTranslator());
        $this->assertFalse($form->consent);
        $this->assertSame('', $form->password);
    }

    public function testGetAttributeLabels(): void
    {
        $form = new AnonymizeForm($this->createTranslator());
        $labels = $form->getAttributeLabels();
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('consent', $labels);
    }

    public function testGetFormName(): void
    {
        $form = new AnonymizeForm($this->createTranslator());
        $this->assertSame('anonymize', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new AnonymizeForm($this->createTranslator());
        $this->assertSame($form->getAttributeLabels(), $form->getPropertyLabels());
    }

    public function testSetProperties(): void
    {
        $form = new AnonymizeForm($this->createTranslator());
        $form->consent = true;
        $form->password = 'mypassword';
        $this->assertTrue($form->consent);
        $this->assertSame('mypassword', $form->password);
    }
}
