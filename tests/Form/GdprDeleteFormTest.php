<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Yiisoft\Validator\Validator;
use YiiRocks\Voyti\Form\GdprDeleteForm;

final class GdprDeleteFormTest extends TestCase
{
    public function testValidData(): void
    {
        $validator = new Validator();
        $form = new GdprDeleteForm();
        $form->password = 'currentpassword';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    public function testEmptyPassword(): void
    {
        $validator = new Validator();
        $form = new GdprDeleteForm();
        $form->password = '';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testGetFormName(): void
    {
        $form = new GdprDeleteForm();
        $this->assertSame('gdpr-delete', $form->getFormName());
    }

    public function testGetAttributeLabels(): void
    {
        $form = new GdprDeleteForm();
        $labels = $form->getAttributeLabels();

        $this->assertArrayHasKey('password', $labels);
        $this->assertSame('Current password', $labels['password']);
    }

    public function testDefaults(): void
    {
        $form = new GdprDeleteForm();
        $this->assertSame('', $form->password);
    }

    public function testPropertyAccess(): void
    {
        $form = new GdprDeleteForm();
        $form->password = 'mypassword';

        $this->assertSame('mypassword', $form->getPropertyValue('password'));
    }
}
