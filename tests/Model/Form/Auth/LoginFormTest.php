<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Auth;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Recaptcha\RecaptchaV3Rule;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Model\Form\Auth\LoginForm;
use YiiRocks\Voyti\tests\Support\RecaptchaRegistryTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Validator;

#[AllowMockObjectsWithoutExpectations]
final class LoginFormTest extends TestCase
{
    use RecaptchaRegistryTrait;

    public function testGetPropertyLabels(): void
    {
        $form = new LoginForm(VoytiConfigFactory::create(), $this->createTranslator());
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('login', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('rememberMe', $labels);
    }

    public function testGetRules(): void
    {
        $config = VoytiConfigFactory::create(recaptchaVersion: RecaptchaVersion::V3);
        $form = new LoginForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertIsArray($rules);
        $this->assertArrayHasKey('login', $rules);
        $this->assertArrayHasKey('password', $rules);
        // The password rule is Required on the non-2FA login step.
        $this->assertCount(1, $rules['password']);
        $this->assertInstanceOf(Required::class, $rules['password'][0]);
        $this->assertArrayNotHasKey('twoFactorAuthenticationCode', $rules);
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertCount(1, $rules['gRecaptchaResponse']);
        $rule = $rules['gRecaptchaResponse'][0];
        $this->assertInstanceOf(RecaptchaV3Rule::class, $rule);
        $this->assertSame(0.5, $rule->getThreshold());
        $this->assertSame('voyti_login', $rule->getAction());
    }

    public function testValidationErrorMessageUsesPropertyLabelNotRawPropertyName(): void
    {
        $this->configureRecaptchaRegistry();
        $form = new LoginForm(VoytiConfigFactory::create(), $this->createTranslator());
        $result = (new Validator())->validate($form);

        $messages = $result->getErrorMessagesIndexedByProperty();
        $this->assertArrayHasKey('login', $messages);
        $this->assertSame('Username or Email cannot be blank.', $messages['login'][0]);
    }
}
