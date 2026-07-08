<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form\Auth;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Form\Auth\ResendForm;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class ResendFormTest extends TestCase
{

    public function testConstruct(): void
    {
        $form = new ResendForm(new ModuleConfig(), $this->createTranslator());
        $this->assertSame('', $form->email);
        $this->assertSame('', $form->gRecaptchaResponse);
    }

    public function testGetAttributeLabels(): void
    {
        $form = new ResendForm(new ModuleConfig(), $this->createTranslator());
        $labels = $form->getAttributeLabels();
        $this->assertArrayHasKey('email', $labels);
    }

    public function testGetFormName(): void
    {
        $form = new ResendForm(new ModuleConfig(), $this->createTranslator());
        $this->assertSame('resend', $form->getFormName());
    }

    public function testGetRules(): void
    {
        $form = new ResendForm(new ModuleConfig(), $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayNotHasKey('gRecaptchaResponse', $rules);
    }

    public function testGetRulesWithRecaptchaV2(): void
    {
        $config = new ModuleConfig(recaptchaVersion: 'v2');
        $form = new ResendForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertInstanceOf(\YiiRocks\Recaptcha\RecaptchaV2Rule::class, $rules['gRecaptchaResponse'][0]);
    }

    public function testGetRulesWithRecaptchaV3(): void
    {
        $config = new ModuleConfig(recaptchaVersion: 'v3');
        $form = new ResendForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $rule = $rules['gRecaptchaResponse'][0];
        $this->assertInstanceOf(\YiiRocks\Recaptcha\RecaptchaV3Rule::class, $rule);
        $this->assertSame(0.5, $rule->getThreshold());
        $this->assertSame('voyti_resend', $rule->getAction());
    }

    public function testSetEmail(): void
    {
        $form = new ResendForm(new ModuleConfig(), $this->createTranslator());
        $form->email = 'user@example.com';
        $this->assertSame('user@example.com', $form->email);
    }
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(
            fn (string $id) => $id,
        );
        return $translator;
    }
}
