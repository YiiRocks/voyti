<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Auth;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Recaptcha\RecaptchaV2Rule;
use YiiRocks\Recaptcha\RecaptchaV3Rule;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Model\Form\Auth\ResendForm;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;

#[AllowMockObjectsWithoutExpectations]
final class ResendFormTest extends TestCase
{
    use TranslatorMockTrait;

    public function testConstruct(): void
    {
        $form = new ResendForm(ModuleConfigFactory::create(), $this->createTranslator());
        $this->assertSame('', $form->email);
        $this->assertSame('', $form->gRecaptchaResponse);
    }

    public function testGetFormName(): void
    {
        $form = new ResendForm(ModuleConfigFactory::create(), $this->createTranslator());
        $this->assertSame('resend', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new ResendForm(ModuleConfigFactory::create(), $this->createTranslator());
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('email', $labels);
    }

    public function testGetRules(): void
    {
        $form = new ResendForm(ModuleConfigFactory::create(), $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayNotHasKey('gRecaptchaResponse', $rules);
    }

    public function testGetRulesWithRecaptchaV2(): void
    {
        $config = ModuleConfigFactory::create(recaptchaVersion: RecaptchaVersion::V2);
        $form = new ResendForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertInstanceOf(RecaptchaV2Rule::class, $rules['gRecaptchaResponse'][0]);
    }

    public function testGetRulesWithRecaptchaV3(): void
    {
        $config = ModuleConfigFactory::create(recaptchaVersion: RecaptchaVersion::V3);
        $form = new ResendForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $rule = $rules['gRecaptchaResponse'][0];
        $this->assertInstanceOf(RecaptchaV3Rule::class, $rule);
        $this->assertSame(0.5, $rule->getThreshold());
        $this->assertSame('voyti_resend', $rule->getAction());
    }

    public function testGetValidationPropertyLabels(): void
    {
        $form = new ResendForm(ModuleConfigFactory::create(), $this->createTranslator());
        $this->assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
    }

    public function testSetEmail(): void
    {
        $form = new ResendForm(ModuleConfigFactory::create(), $this->createTranslator());
        $form->email = 'user@example.com';
        $this->assertSame('user@example.com', $form->email);
    }
}
