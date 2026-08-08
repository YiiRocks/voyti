<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Auth;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Recaptcha\RecaptchaRegistry;
use YiiRocks\Recaptcha\RecaptchaV2Rule;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\tests\Support\RecaptchaRegistryTrait;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\TrueValue;

#[AllowMockObjectsWithoutExpectations]
final class RegistrationFormTest extends TestCase
{
    use RecaptchaRegistryTrait;
    use TranslatorMockTrait;

    protected function tearDown(): void
    {
        RecaptchaRegistry::reset();
    }

    public function testGetPropertyLabels(): void
    {
        $form = new RegistrationForm(VoytiConfigFactory::create(), $this->createTranslator());
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('username', $labels);
        $this->assertArrayHasKey('email', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('passwordRepeat', $labels);
        $this->assertArrayHasKey('gdprConsent', $labels);
    }

    public function testGetRulesWithGdprEnabled(): void
    {
        $config = VoytiConfigFactory::create(enableGdprCompliance: true);
        $form = new RegistrationForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('gdprConsent', $rules);
        $rule = $rules['gdprConsent'][0];
        $this->assertInstanceOf(TrueValue::class, $rule);
        $this->assertTrue($rule->getTrueValue());
    }

    public function testGetRulesWithPasswordComplexityDisabled(): void
    {
        $config = VoytiConfigFactory::create(enablePasswordComplexity: false);
        $form = new RegistrationForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertCount(1, $rules['password']);
    }

    public function testGetRulesWithPasswordComplexityEnabled(): void
    {
        $config = VoytiConfigFactory::create(enablePasswordComplexity: true);
        $form = new RegistrationForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertCount(2, $rules['password']);
        $this->assertInstanceOf(Regex::class, $rules['password'][1]);
    }

    public function testGetRulesWithRecaptchaV2(): void
    {
        $this->configureRecaptchaRegistry();
        $config = VoytiConfigFactory::create(recaptchaVersion: RecaptchaVersion::V2);
        $form = new RegistrationForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertInstanceOf(RecaptchaV2Rule::class, $rules['gRecaptchaResponse'][0]);
    }
}
