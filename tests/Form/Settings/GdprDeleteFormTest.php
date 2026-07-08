<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form\Settings;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Form\Settings\GdprDeleteForm;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class GdprDeleteFormTest extends TestCase
{

    public function testConstruct(): void
    {
        $form = new GdprDeleteForm($this->createTranslator());
        $this->assertFalse($form->consent);
        $this->assertSame('', $form->password);
    }

    public function testGetAttributeLabels(): void
    {
        $form = new GdprDeleteForm($this->createTranslator());
        $labels = $form->getAttributeLabels();
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('consent', $labels);
    }

    public function testGetFormName(): void
    {
        $form = new GdprDeleteForm($this->createTranslator());
        $this->assertSame('gdpr-delete', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new GdprDeleteForm($this->createTranslator());
        $this->assertSame($form->getAttributeLabels(), $form->getPropertyLabels());
    }

    public function testSetProperties(): void
    {
        $form = new GdprDeleteForm($this->createTranslator());
        $form->consent = true;
        $form->password = 'mypassword';
        $this->assertTrue($form->consent);
        $this->assertSame('mypassword', $form->password);
    }
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(
            fn (string $id) => $id,
        );
        return $translator;
    }
}
