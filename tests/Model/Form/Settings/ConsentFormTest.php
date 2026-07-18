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

    public function testAnonymizeFormDefaults(): void
    {
        $form = new ConsentForm($this->createTranslator(), 'anonymize', 'voyti.view.anonymize.confirm_label');
        $this->assertFalse($form->consent);
        $this->assertSame('', $form->password);
    }

    public function testAnonymizeFormGetFormName(): void
    {
        $form = new ConsentForm($this->createTranslator(), 'anonymize', 'voyti.view.anonymize.confirm_label');
        $this->assertSame('anonymize', $form->getFormName());
    }

    public function testDeleteAccountFormGetFormName(): void
    {
        $form = new ConsentForm($this->createTranslator(), 'delete-account', 'voyti.view.delete_account.confirm_label');
        $this->assertSame('delete-account', $form->getFormName());
    }

    public function testGetAttributeLabels(): void
    {
        $form = new ConsentForm($this->createTranslator(), 'test', 'voyti.view.anonymize.confirm_label');
        $labels = $form->getAttributeLabels();
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('consent', $labels);
    }

    public function testGetPropertyLabels(): void
    {
        $form = new ConsentForm($this->createTranslator(), 'test', 'voyti.view.anonymize.confirm_label');
        $this->assertSame($form->getAttributeLabels(), $form->getPropertyLabels());
    }

    public function testSetProperties(): void
    {
        $form = new ConsentForm($this->createTranslator(), 'test', 'voyti.view.anonymize.confirm_label');
        $form->consent = true;
        $form->password = 'mypassword';
        $this->assertTrue($form->consent);
        $this->assertSame('mypassword', $form->password);
    }
}
