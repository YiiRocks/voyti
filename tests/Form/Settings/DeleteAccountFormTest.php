<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form\Settings;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Form\Settings\DeleteAccountForm;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class DeleteAccountFormTest extends TestCase
{

    public function testConstruct(): void
    {
        $form = new DeleteAccountForm($this->createTranslator());
        $this->assertFalse($form->consent);
        $this->assertSame('', $form->password);
    }

    public function testGetAttributeLabels(): void
    {
        $form = new DeleteAccountForm($this->createTranslator());
        $labels = $form->getAttributeLabels();
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('consent', $labels);
    }

    public function testGetFormName(): void
    {
        $form = new DeleteAccountForm($this->createTranslator());
        $this->assertSame('delete-account', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new DeleteAccountForm($this->createTranslator());
        $this->assertSame($form->getAttributeLabels(), $form->getPropertyLabels());
    }

    public function testSetProperties(): void
    {
        $form = new DeleteAccountForm($this->createTranslator());
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
