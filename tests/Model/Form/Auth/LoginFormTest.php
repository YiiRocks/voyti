<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Auth;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Recaptcha\RecaptchaV3Rule;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Model\Form\Auth\LoginForm;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Validator;

#[AllowMockObjectsWithoutExpectations]
final class LoginFormTest extends TestCase
{
    public function testConstruct(): void
    {
        $config = ModuleConfigFactory::create();
        $form = new LoginForm($config, $this->createTranslator());
        $this->assertSame('', $form->login);
        $this->assertSame('', $form->password);
        $this->assertFalse($form->rememberMe);
        $this->assertNull($form->twoFactorAuthenticationCode);
        $this->assertSame('', $form->gRecaptchaResponse);
    }

    public function testGetFormName(): void
    {
        $form = new LoginForm(ModuleConfigFactory::create(), $this->createTranslator());
        $this->assertSame('login', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new LoginForm(ModuleConfigFactory::create(), $this->createTranslator());
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('login', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('rememberMe', $labels);
        $this->assertArrayHasKey('twoFactorAuthenticationCode', $labels);
    }

    public function testGetRulesReturnsLoginRules(): void
    {
        $form = new LoginForm(ModuleConfigFactory::create(), $this->createTranslator());
        $rules = $form->getRules();
        $this->assertIsArray($rules);
        $this->assertArrayHasKey('login', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertArrayNotHasKey('gRecaptchaResponse', $rules);
        $this->assertArrayNotHasKey('twoFactorAuthenticationCode', $rules);
    }

    public function testGetRulesWithRecaptchaV2(): void
    {
        $config = ModuleConfigFactory::create(recaptchaVersion: RecaptchaVersion::V2);
        $form = new LoginForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertCount(1, $rules['gRecaptchaResponse']);
    }

    public function testGetRulesWithRecaptchaV3(): void
    {
        $config = ModuleConfigFactory::create(recaptchaVersion: RecaptchaVersion::V3);
        $form = new LoginForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertCount(1, $rules['gRecaptchaResponse']);
        $rule = $rules['gRecaptchaResponse'][0];
        $this->assertInstanceOf(RecaptchaV3Rule::class, $rule);
        $this->assertSame(0.5, $rule->getThreshold());
        $this->assertSame('voyti_login', $rule->getAction());
    }

    public function testGetRulesWithRequireTwoFactorAuthenticationCode(): void
    {
        $form = new LoginForm(ModuleConfigFactory::create(), $this->createTranslator(), requireTwoFactorAuthenticationCode: true);
        $rules = $form->getRules();
        $this->assertArrayHasKey('twoFactorAuthenticationCode', $rules);
        $this->assertCount(1, $rules['twoFactorAuthenticationCode']);
        $this->assertInstanceOf(Required::class, $rules['twoFactorAuthenticationCode'][0]);
    }

    public function testGetValidationPropertyLabels(): void
    {
        $form = new LoginForm(ModuleConfigFactory::create(), $this->createTranslator());
        $this->assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
    }

    public function testRememberMeDefaultsToFalse(): void
    {
        $form = new LoginForm(ModuleConfigFactory::create(), $this->createTranslator());
        $this->assertFalse($form->rememberMe);
    }

    public function testSetPropertiesViaPublicAccess(): void
    {
        $form = new LoginForm(ModuleConfigFactory::create(), $this->createTranslator());
        $form->login = 'testuser';
        $form->password = 'secret';
        $form->rememberMe = true;
        $form->twoFactorAuthenticationCode = '123456';

        $this->assertSame('testuser', $form->login);
        $this->assertSame('secret', $form->password);
        $this->assertTrue($form->rememberMe);
        $this->assertSame('123456', $form->twoFactorAuthenticationCode);
    }

    public function testValidationErrorMessageUsesPropertyLabelNotRawPropertyName(): void
    {
        $form = new LoginForm(ModuleConfigFactory::create(), $this->createTranslator());
        $result = (new Validator())->validate($form);

        $messages = $result->getErrorMessagesIndexedByProperty();
        $this->assertArrayHasKey('login', $messages);
        $this->assertSame('Username or Email cannot be blank.', $messages['login'][0]);
    }
}
