<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Yiisoft\Validator\Validator;
use YiiRocks\Voyti\Form\ResendForm;
use YiiRocks\Voyti\ModuleConfig;

final class ResendFormTest extends TestCase
{
    private function createForm(ModuleConfig $config = new ModuleConfig()): ResendForm
    {
        return new ResendForm($config);
    }

    public function testValidData(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = 'user@example.com';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    public function testEmptyEmail(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = '';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('email'));
    }

    public function testInvalidEmail(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = 'not-an-email';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('email'));
    }

    public function testGetFormName(): void
    {
        $form = $this->createForm();
        $this->assertSame('resend', $form->getFormName());
    }

    public function testGetAttributeLabels(): void
    {
        $form = $this->createForm();
        $labels = $form->getAttributeLabels();

        $this->assertArrayHasKey('email', $labels);
        $this->assertSame('Email', $labels['email']);
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
        $this->assertSame('', $form->email);
        $this->assertSame('', $form->gRecaptchaResponse);
    }

    public function testPropertyAccess(): void
    {
        $form = $this->createForm();
        $form->email = 'test@example.com';
        $form->gRecaptchaResponse = 'test-token';

        $this->assertSame('test@example.com', $form->getPropertyValue('email'));
        $this->assertSame('test-token', $form->getPropertyValue('gRecaptchaResponse'));
    }
}
