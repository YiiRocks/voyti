<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Yiisoft\Validator\Validator;
use YiiRocks\Voyti\Form\RecoveryForm;
use YiiRocks\Voyti\ModuleConfig;

final class RecoveryFormTest extends TestCase
{
    private function createForm(ModuleConfig $config = new ModuleConfig(), string $scenario = RecoveryForm::SCENARIO_REQUEST): RecoveryForm
    {
        return new RecoveryForm($config, $scenario);
    }

    public function testValidData(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = 'user@example.com';
        $form->password = 'newsecret';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    public function testEmptyEmail(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = '';
        $form->password = 'newsecret';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('email'));
    }

    public function testInvalidEmail(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = 'not-email';
        $form->password = 'newsecret';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('email'));
    }

    public function testEmptyPassword(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = 'user@example.com';
        $form->password = '';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testShortPassword(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = 'user@example.com';
        $form->password = '12345';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testLongPassword(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = 'user@example.com';
        $form->password = str_repeat('a', 73);

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

    public function testScenariosConstants(): void
    {
        $this->assertSame('request', RecoveryForm::SCENARIO_REQUEST);
        $this->assertSame('reset', RecoveryForm::SCENARIO_RESET);
    }

    public function testGetFormName(): void
    {
        $form = $this->createForm();
        $this->assertSame('recovery', $form->getFormName());
    }

    public function testGetAttributeLabels(): void
    {
        $form = $this->createForm();
        $labels = $form->getAttributeLabels();

        $this->assertArrayHasKey('email', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertSame('Email', $labels['email']);
        $this->assertSame('Password', $labels['password']);
    }

    public function testDefaults(): void
    {
        $form = $this->createForm();

        $this->assertSame('', $form->email);
        $this->assertSame('', $form->password);
        $this->assertSame('', $form->gRecaptchaResponse);
        $this->assertSame(RecoveryForm::SCENARIO_REQUEST, $form->scenario);
    }

    public function testResetScenarioDefaults(): void
    {
        $form = $this->createForm(scenario: RecoveryForm::SCENARIO_RESET);

        $this->assertSame('', $form->gRecaptchaResponse);
        $this->assertSame(RecoveryForm::SCENARIO_RESET, $form->scenario);
    }

    public function testGetRulesWithRequestScenario(): void
    {
        $form = $this->createForm(scenario: RecoveryForm::SCENARIO_REQUEST);
        $rules = $form->getRules();

        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
    }

    public function testGetRulesWithResetScenario(): void
    {
        $form = $this->createForm(scenario: RecoveryForm::SCENARIO_RESET);
        $rules = $form->getRules();

        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertArrayNotHasKey('gRecaptchaResponse', $rules);
    }

    public function testGetRulesWithRequestScenarioAndRecaptchaV3(): void
    {
        $form = $this->createForm(
            config: new ModuleConfig(recaptchaVersion: 'v3'),
            scenario: RecoveryForm::SCENARIO_REQUEST,
        );
        $rules = $form->getRules();

        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertCount(1, $rules['gRecaptchaResponse']);
        $this->assertInstanceOf(
            \YiiRocks\Recaptcha\RecaptchaV3Rule::class,
            $rules['gRecaptchaResponse'][0],
        );
    }

    public function testGetRulesWithRequestScenarioAndRecaptchaV2(): void
    {
        $form = $this->createForm(
            config: new ModuleConfig(recaptchaVersion: 'v2'),
            scenario: RecoveryForm::SCENARIO_REQUEST,
        );
        $rules = $form->getRules();

        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertCount(1, $rules['gRecaptchaResponse']);
        $this->assertInstanceOf(
            \YiiRocks\Recaptcha\RecaptchaV2Rule::class,
            $rules['gRecaptchaResponse'][0],
        );
    }

    public function testPropertyAccess(): void
    {
        $form = $this->createForm();
        $form->email = 'user@example.com';
        $form->password = 'secret123';
        $form->gRecaptchaResponse = 'test-token';

        $this->assertSame('user@example.com', $form->getPropertyValue('email'));
        $this->assertSame('secret123', $form->getPropertyValue('password'));
        $this->assertSame('test-token', $form->getPropertyValue('gRecaptchaResponse'));
    }
}
