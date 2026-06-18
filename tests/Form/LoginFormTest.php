<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Yiisoft\Validator\Validator;
use YiiRocks\Voyti\Form\LoginForm;
use YiiRocks\Voyti\ModuleConfig;

final class LoginFormTest extends TestCase
{
    private function createForm(ModuleConfig $config = new ModuleConfig()): LoginForm
    {
        return new LoginForm($config);
    }

    public function testValidData(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->login = 'testuser';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    public function testEmptyLogin(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->login = '';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('login'));
    }

    public function testEmptyPassword(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->login = 'testuser';
        $form->password = '';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testEmptyAll(): void
    {
        $validator = new Validator();
        $form = $this->createForm();

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
    }

    public function testOptionalFields(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->login = 'testuser';
        $form->password = 'secret123';
        $form->rememberMe = true;
        $form->twoFactorAuthenticationCode = '123456';

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

    public function testDefaults(): void
    {
        $form = $this->createForm();

        $this->assertSame('', $form->login);
        $this->assertSame('', $form->password);
        $this->assertFalse($form->rememberMe);
        $this->assertNull($form->twoFactorAuthenticationCode);
        $this->assertSame('', $form->gRecaptchaResponse);
    }

    public function testPropertyAccess(): void
    {
        $form = $this->createForm();
        $form->login = 'testuser';
        $form->password = 'secret123';
        $form->rememberMe = true;
        $form->twoFactorAuthenticationCode = '654321';
        $form->gRecaptchaResponse = 'test-token';

        $this->assertSame('testuser', $form->getPropertyValue('login'));
        $this->assertSame('secret123', $form->getPropertyValue('password'));
        $this->assertTrue($form->getPropertyValue('rememberMe'));
        $this->assertSame('654321', $form->getPropertyValue('twoFactorAuthenticationCode'));
        $this->assertSame('test-token', $form->getPropertyValue('gRecaptchaResponse'));
    }

    public function testPropertyLabels(): void
    {
        $form = $this->createForm();
        $labels = $form->getPropertyLabels();

        $this->assertEmpty($labels);
    }
}
