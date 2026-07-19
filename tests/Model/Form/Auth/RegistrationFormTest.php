<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Auth;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Recaptcha\RecaptchaV2Rule;
use YiiRocks\Recaptcha\RecaptchaV3Rule;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\TrueValue;

#[AllowMockObjectsWithoutExpectations]
final class RegistrationFormTest extends TestCase
{
    use TranslatorMockTrait;

    public function testConstruct(): void
    {
        $form = new RegistrationForm(new ModuleConfig(), $this->createTranslator());
        $this->assertSame('', $form->email);
        $this->assertSame('', $form->username);
        $this->assertSame('', $form->password);
        $this->assertSame('', $form->passwordRepeat);
        $this->assertFalse($form->gdprConsent);
    }

    public function testGetFormName(): void
    {
        $form = new RegistrationForm(new ModuleConfig(), $this->createTranslator());
        $this->assertSame('register', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new RegistrationForm(new ModuleConfig(), $this->createTranslator());
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('username', $labels);
        $this->assertArrayHasKey('email', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('passwordRepeat', $labels);
        $this->assertArrayHasKey('gdprConsent', $labels);
    }

    public function testGetRules(): void
    {
        $form = new RegistrationForm(new ModuleConfig(), $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('username', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertArrayHasKey('passwordRepeat', $rules);
    }

    public function testGetRulesWithGdprDisabled(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: false);
        $form = new RegistrationForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayNotHasKey('gdprConsent', $rules);
    }

    public function testGetRulesWithGdprEnabled(): void
    {
        $config = new ModuleConfig(enableGdprCompliance: true);
        $form = new RegistrationForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('gdprConsent', $rules);
        $rule = $rules['gdprConsent'][0];
        $this->assertInstanceOf(TrueValue::class, $rule);
        $this->assertTrue($rule->getTrueValue());
    }

    public function testGetRulesWithoutRecaptcha(): void
    {
        $config = new ModuleConfig(recaptchaVersion: null);
        $form = new RegistrationForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayNotHasKey('gRecaptchaResponse', $rules);
    }

    public function testGetRulesWithPasswordComplexityDisabled(): void
    {
        $config = new ModuleConfig(enablePasswordComplexity: false);
        $form = new RegistrationForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertCount(1, $rules['password']);
    }

    public function testGetRulesWithPasswordComplexityEnabled(): void
    {
        $config = new ModuleConfig(enablePasswordComplexity: true);
        $form = new RegistrationForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertCount(2, $rules['password']);
        $this->assertInstanceOf(Regex::class, $rules['password'][1]);
    }

    public function testGetRulesWithRecaptchaV2(): void
    {
        $config = new ModuleConfig(recaptchaVersion: RecaptchaVersion::V2);
        $form = new RegistrationForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertInstanceOf(RecaptchaV2Rule::class, $rules['gRecaptchaResponse'][0]);
    }

    public function testGetRulesWithRecaptchaV3(): void
    {
        $config = new ModuleConfig(recaptchaVersion: RecaptchaVersion::V3);
        $form = new RegistrationForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $rule = $rules['gRecaptchaResponse'][0];
        $this->assertInstanceOf(RecaptchaV3Rule::class, $rule);
        $this->assertSame(0.5, $rule->getThreshold());
        $this->assertSame('voyti_register', $rule->getAction());
    }

    public function testGetValidationPropertyLabels(): void
    {
        $form = new RegistrationForm(new ModuleConfig(), $this->createTranslator());
        $this->assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
    }

    public function testSetProperties(): void
    {
        $form = new RegistrationForm(new ModuleConfig(), $this->createTranslator());
        $form->email = 'user@example.com';
        $form->username = 'johndoe';
        $form->password = 'secret123';
        $form->passwordRepeat = 'secret123';
        $form->gdprConsent = true;

        $this->assertSame('user@example.com', $form->email);
        $this->assertSame('johndoe', $form->username);
        $this->assertSame('secret123', $form->password);
        $this->assertSame('secret123', $form->passwordRepeat);
        $this->assertTrue($form->gdprConsent);
    }
}
