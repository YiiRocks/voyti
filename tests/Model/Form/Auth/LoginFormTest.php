<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Auth;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Model\Form\Auth\LoginForm;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\Required;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class LoginFormTest extends TestCase
{

    public function testConstruct(): void
    {
        $config = new ModuleConfig();
        $form = new LoginForm($config, $this->createTranslator());
        $this->assertSame('', $form->login);
        $this->assertSame('', $form->password);
        $this->assertFalse($form->rememberMe);
        $this->assertNull($form->twoFactorAuthenticationCode);
        $this->assertSame('', $form->gRecaptchaResponse);
    }

    public function testGetAttributeLabels(): void
    {
        $form = new LoginForm(new ModuleConfig(), $this->createTranslator());
        $labels = $form->getAttributeLabels();
        $this->assertArrayHasKey('login', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('rememberMe', $labels);
        $this->assertArrayHasKey('twoFactorAuthenticationCode', $labels);
    }

    public function testGetFormName(): void
    {
        $form = new LoginForm(new ModuleConfig(), $this->createTranslator());
        $this->assertSame('login', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new LoginForm(new ModuleConfig(), $this->createTranslator());
        $this->assertSame($form->getAttributeLabels(), $form->getPropertyLabels());
    }

    public function testGetRulesReturnsLoginRules(): void
    {
        $form = new LoginForm(new ModuleConfig(), $this->createTranslator());
        $rules = $form->getRules();
        $this->assertIsArray($rules);
        $this->assertArrayHasKey('login', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertArrayNotHasKey('gRecaptchaResponse', $rules);
        $this->assertArrayNotHasKey('twoFactorAuthenticationCode', $rules);
    }

    public function testGetRulesWithRecaptchaV2(): void
    {
        $config = new ModuleConfig(recaptchaVersion: RecaptchaVersion::V2);
        $form = new LoginForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertCount(1, $rules['gRecaptchaResponse']);
    }

    public function testGetRulesWithRecaptchaV3(): void
    {
        $config = new ModuleConfig(recaptchaVersion: RecaptchaVersion::V3);
        $form = new LoginForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertCount(1, $rules['gRecaptchaResponse']);
        $rule = $rules['gRecaptchaResponse'][0];
        $this->assertInstanceOf(\YiiRocks\Recaptcha\RecaptchaV3Rule::class, $rule);
        $this->assertSame(0.5, $rule->getThreshold());
        $this->assertSame('voyti_login', $rule->getAction());
    }

    public function testGetRulesWithRequireTwoFactorAuthenticationCode(): void
    {
        $form = new LoginForm(new ModuleConfig(), $this->createTranslator(), requireTwoFactorAuthenticationCode: true);
        $rules = $form->getRules();
        $this->assertArrayHasKey('twoFactorAuthenticationCode', $rules);
        $this->assertCount(1, $rules['twoFactorAuthenticationCode']);
        $this->assertInstanceOf(Required::class, $rules['twoFactorAuthenticationCode'][0]);
    }

    public function testRememberMeDefaultsToFalse(): void
    {
        $form = new LoginForm(new ModuleConfig(), $this->createTranslator());
        $this->assertFalse($form->rememberMe);
    }

    public function testSetPropertiesViaPublicAccess(): void
    {
        $form = new LoginForm(new ModuleConfig(), $this->createTranslator());
        $form->login = 'testuser';
        $form->password = 'secret';
        $form->rememberMe = true;
        $form->twoFactorAuthenticationCode = '123456';

        $this->assertSame('testuser', $form->login);
        $this->assertSame('secret', $form->password);
        $this->assertTrue($form->rememberMe);
        $this->assertSame('123456', $form->twoFactorAuthenticationCode);
    }
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('translate')->willReturnCallback(
            fn (string $id, array $parameters = [], string $category = 'voyti') => $id,
        );
        return $translator;
    }
}
