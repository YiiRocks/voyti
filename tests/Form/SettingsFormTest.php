<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Yiisoft\Validator\Validator;
use YiiRocks\Voyti\Form\SettingsForm;

final class SettingsFormTest extends TestCase
{
    public function testValidData(): void
    {
        $validator = new Validator();
        $form = new SettingsForm();
        $form->username = 'newuser';
        $form->email = 'new@example.com';
        $form->password = 'newsecret';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    public function testValidWithEmptyUsernameAndEmail(): void
    {
        $validator = new Validator();
        $form = new SettingsForm();
        $form->password = 'newsecret';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    public function testEmptyPasswordFails(): void
    {
        $validator = new Validator();
        $form = new SettingsForm();
        $form->username = 'newuser';
        $form->email = 'new@example.com';
        $form->password = '';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testShortPasswordFails(): void
    {
        $validator = new Validator();
        $form = new SettingsForm();
        $form->username = 'newuser';
        $form->email = 'new@example.com';
        $form->password = '12345';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testLongPasswordFails(): void
    {
        $validator = new Validator();
        $form = new SettingsForm();
        $form->username = 'newuser';
        $form->email = 'new@example.com';
        $form->password = str_repeat('a', 73);

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testEmptyFormFailsDueToPassword(): void
    {
        $validator = new Validator();
        $form = new SettingsForm();

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testGetFormName(): void
    {
        $form = new SettingsForm();
        $this->assertSame('settings', $form->getFormName());
    }

    public function testGetAttributeLabels(): void
    {
        $form = new SettingsForm();
        $labels = $form->getAttributeLabels();

        $this->assertArrayHasKey('username', $labels);
        $this->assertArrayHasKey('email', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertSame('Username', $labels['username']);
        $this->assertSame('Email', $labels['email']);
        $this->assertSame('New password', $labels['password']);
    }

    public function testDefaults(): void
    {
        $form = new SettingsForm();

        $this->assertSame('', $form->username);
        $this->assertSame('', $form->email);
        $this->assertSame('', $form->password);
    }

    public function testPropertyAccess(): void
    {
        $form = new SettingsForm();
        $form->username = 'newuser';
        $form->email = 'new@example.com';
        $form->password = 'newsecret';

        $this->assertSame('newuser', $form->getPropertyValue('username'));
        $this->assertSame('new@example.com', $form->getPropertyValue('email'));
        $this->assertSame('newsecret', $form->getPropertyValue('password'));
    }
}
