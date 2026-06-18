<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\Validator\Validator;
use YiiRocks\Voyti\Form\RegistrationForm;
use YiiRocks\Voyti\ModuleConfig;

final class RegistrationFormTest extends TestCase
{
    private function createForm(ModuleConfig $config = new ModuleConfig()): RegistrationForm
    {
        $reflection = new ReflectionClass(RegistrationForm::class);
        $form = $reflection->newInstanceWithoutConstructor();

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($form, $config);

        return $form;
    }

    public function testValidData(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = 'test@example.com';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    public function testShortUsername(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'ab';
        $form->email = 'test@example.com';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('username'));
    }

    public function testInvalidUsernameCharacters(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'user name!';
        $form->email = 'test@example.com';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('username'));
    }

    public function testMissingUsername(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = '';
        $form->email = 'test@example.com';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('username'));
    }

    public function testInvalidEmail(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = 'not-an-email';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('email'));
    }

    public function testMissingEmail(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = '';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('email'));
    }

    public function testShortPassword(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = 'test@example.com';
        $form->password = '12345';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testLongPassword(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = 'test@example.com';
        $form->password = str_repeat('a', 73);

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testEmptyPasswordFails(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = 'test@example.com';
        $form->password = '';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testGdprConsentOptional(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = 'test@example.com';
        $form->password = 'secret123';
        $form->gdprConsent = true;

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    public function testGetRulesWithRecaptchaEnabled(): void
    {
        $config = new ModuleConfig(recaptchaVersion: 'v3');
        $form = $this->createForm($config);
        $rules = $form->getRules();

        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertCount(1, $rules['gRecaptchaResponse']);
        $this->assertInstanceOf(
            \YiiRocks\Recaptcha\RecaptchaV3Rule::class,
            $rules['gRecaptchaResponse'][0],
        );
    }

    public function testIsPasswordAutoGenerated(): void
    {
        $configDefault = new ModuleConfig(generatePasswords: false);
        $formDefault = $this->createForm($configDefault);
        $this->assertFalse($formDefault->isPasswordAutoGenerated());

        $configGen = new ModuleConfig(generatePasswords: true);
        $formGen = $this->createForm($configGen);
        $this->assertTrue($formGen->isPasswordAutoGenerated());
    }

    public function testGetFormName(): void
    {
        $form = $this->createForm();
        $this->assertSame('register', $form->getFormName());
    }

    public function testGetAttributeLabels(): void
    {
        $form = $this->createForm();
        $labels = $form->getAttributeLabels();

        $this->assertArrayHasKey('username', $labels);
        $this->assertArrayHasKey('email', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('gdprConsent', $labels);
        $this->assertSame('Username', $labels['username']);
        $this->assertSame('Email', $labels['email']);
        $this->assertSame('Password', $labels['password']);
        $this->assertSame('Data processing consent', $labels['gdprConsent']);
    }

    public function testDefaults(): void
    {
        $form = $this->createForm();

        $this->assertSame('', $form->username);
        $this->assertSame('', $form->email);
        $this->assertSame('', $form->password);
        $this->assertFalse($form->gdprConsent);
    }

    public function testPropertyAccess(): void
    {
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = 'test@example.com';
        $form->password = 'secret123';
        $form->gdprConsent = true;

        $this->assertSame('testuser', $form->getPropertyValue('username'));
        $this->assertSame('test@example.com', $form->getPropertyValue('email'));
        $this->assertSame('secret123', $form->getPropertyValue('password'));
        $this->assertTrue($form->getPropertyValue('gdprConsent'));
    }
}
