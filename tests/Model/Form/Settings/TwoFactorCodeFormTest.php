<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Settings;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\Form\Settings\TwoFactorCodeForm;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;

#[AllowMockObjectsWithoutExpectations]
final class TwoFactorCodeFormTest extends TestCase
{
    use TranslatorMockTrait;

    public function testConstruct(): void
    {
        $form = new TwoFactorCodeForm($this->createTranslator(), 'google');
        $this->assertSame('', $form->code);
        $this->assertSame('google', $form->method);
    }

    public function testGetFormName(): void
    {
        $form = new TwoFactorCodeForm($this->createTranslator(), 'google');
        $this->assertSame('', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new TwoFactorCodeForm($this->createTranslator(), 'email');
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('code', $labels);
    }

    public function testGetValidationPropertyLabels(): void
    {
        $form = new TwoFactorCodeForm($this->createTranslator(), 'google');
        $this->assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
    }

    public function testSetProperties(): void
    {
        $form = new TwoFactorCodeForm($this->createTranslator(), 'email');
        $form->code = '123456';
        $this->assertSame('123456', $form->code);
    }
}
