<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form\Settings;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Form\Settings\TwoFactorCodeForm;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TwoFactorCodeFormTest extends TestCase
{

    public function testConstruct(): void
    {
        $form = new TwoFactorCodeForm($this->createTranslator(), 'google');
        $this->assertSame('', $form->code);
        $this->assertSame('google', $form->method);
    }

    public function testGetAttributeLabels(): void
    {
        $form = new TwoFactorCodeForm($this->createTranslator(), 'email');
        $labels = $form->getAttributeLabels();
        $this->assertArrayHasKey('code', $labels);
    }

    public function testGetFormName(): void
    {
        $form = new TwoFactorCodeForm($this->createTranslator(), 'google');
        $this->assertSame('', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new TwoFactorCodeForm($this->createTranslator(), 'google');
        $this->assertSame($form->getAttributeLabels(), $form->getPropertyLabels());
    }

    public function testSetProperties(): void
    {
        $form = new TwoFactorCodeForm($this->createTranslator(), 'email');
        $form->code = '123456';
        $this->assertSame('123456', $form->code);
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
