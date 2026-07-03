<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Stringable;
use YiiRocks\Voyti\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\CompareType;
use Yiisoft\Validator\Rule\Equal;
use Yiisoft\Validator\Validator;

final class RegistrationFormTest extends TestCase
{

    public function testDefaults(): void
    {
        $form = $this->createForm();

        $this->assertSame('', $form->username);
        $this->assertSame('', $form->email);
        $this->assertSame('', $form->password);
        $this->assertSame('', $form->passwordRepeat);
        $this->assertFalse($form->gdprConsent);
    }

    public function testEmptyPasswordFails(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = 'test@example.com';
        $form->password = '';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testGetAttributeLabels(): void
    {
        $form = $this->createForm();
        $labels = $form->getAttributeLabels();

        $this->assertArrayHasKey('username', $labels);
        $this->assertArrayHasKey('email', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('passwordRepeat', $labels);
        $this->assertArrayHasKey('gdprConsent', $labels);
        $this->assertSame('voyti.view.username_label', $labels['username']);
        $this->assertSame('voyti.view.email_label', $labels['email']);
        $this->assertSame('voyti.view.password_label', $labels['password']);
        $this->assertSame('voyti.view.password_repeat_label', $labels['passwordRepeat']);
        $this->assertSame('voyti.view.registration.gdpr_consent_label', $labels['gdprConsent']);
    }

    public function testGetFormName(): void
    {
        $form = $this->createForm();
        $this->assertSame('register', $form->getFormName());
    }

    public function testGetPropertyLabelForKnownPropertyReturnsLabel(): void
    {
        $form = $this->createForm();

        $this->assertSame('voyti.view.username_label', $form->getPropertyLabel('username'));
        $this->assertSame('voyti.view.email_label', $form->getPropertyLabel('email'));
    }

    public function testGetPropertyLabelForUnknownPropertyFallsBackToParent(): void
    {
        $form = $this->createForm();

        $this->assertSame('G Recaptcha Response', $form->getPropertyLabel('gRecaptchaResponse'));
    }

    public function testGetRulesIncludesStrictStringPasswordRepeatComparison(): void
    {
        $form = $this->createForm();
        $rules = $form->getRules();
        /** @var array<string, list<object>> $rules */
        $this->assertArrayHasKey('passwordRepeat', $rules);
        $passwordRepeatRules = $rules['passwordRepeat'];
        $this->assertCount(1, $passwordRepeatRules);
        $this->assertInstanceOf(Equal::class, $passwordRepeatRules[0]);
        $this->assertSame('password', $passwordRepeatRules[0]->getTargetProperty());
        $this->assertSame('===', $passwordRepeatRules[0]->getOperator());
        $this->assertSame(CompareType::STRING, $passwordRepeatRules[0]->getType());
    }

    public function testGetRulesWithRecaptchaV2(): void
    {
        $config = new ModuleConfig(recaptchaVersion: 'v2');
        $form = $this->createForm($config);
        $rules = $form->getRules();
        /** @var array<string, mixed> $rules */
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        /** @var list<mixed> $gRecaptchaResponse */
        $gRecaptchaResponse = $rules['gRecaptchaResponse'];
        $this->assertCount(1, $gRecaptchaResponse);
        $this->assertInstanceOf(
            \YiiRocks\Recaptcha\RecaptchaV2Rule::class,
            $gRecaptchaResponse[0],
        );
    }

    public function testGetRulesWithRecaptchaV3(): void
    {
        $config = new ModuleConfig(recaptchaVersion: 'v3');
        $form = $this->createForm($config);
        $rules = $form->getRules();
        /** @var array<string, mixed> $rules */
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        /** @var list<mixed> $gRecaptchaResponse */
        $gRecaptchaResponse = $rules['gRecaptchaResponse'];
        $this->assertCount(1, $gRecaptchaResponse);
        $this->assertInstanceOf(
            \YiiRocks\Recaptcha\RecaptchaV3Rule::class,
            $gRecaptchaResponse[0],
        );
        $this->assertSame('voyti_register', $gRecaptchaResponse[0]->getAction());
        $this->assertSame(0.5, $gRecaptchaResponse[0]->getThreshold());
    }

    public function testInvalidEmail(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = 'not-an-email';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('email'));
    }

    public function testInvalidUsernameCharacters(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'user name!';
        $form->email = 'test@example.com';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('username'));
    }

    public function testIsPasswordAutoGenerated(): void
    {
        $configDefault = new ModuleConfig(generatePasswords: false);
        $formDefault = $this->createForm($configDefault);
        $this->assertFalse($formDefault->isPasswordAutoGenerated());

        $configGen = new ModuleConfig(generatePasswords: true);
        $formGen = $this->createForm($configGen);
        $this->assertTrue($formGen->isPasswordAutoGenerated());
    }

    public function testLongPassword(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = 'test@example.com';
        $form->password = str_repeat('a', 73);

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testMissingEmail(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = '';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('email'));
    }

    public function testMissingUsername(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = '';
        $form->email = 'test@example.com';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('username'));
    }

    public function testPasswordRepeatMismatchFails(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = 'test@example.com';
        $form->password = 'secret123';
        $form->passwordRepeat = 'different123';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('passwordRepeat'));
    }

    public function testPropertyAccess(): void
    {
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = 'test@example.com';
        $form->password = 'secret123';
        $form->gdprConsent = true;

        $this->assertSame('testuser', $form->getPropertyValue('username'));
        $this->assertSame('test@example.com', $form->getPropertyValue('email'));
        $this->assertSame('secret123', $form->getPropertyValue('password'));
        $this->assertTrue($form->getPropertyValue('gdprConsent'));
    }

    public function testShortPassword(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = 'test@example.com';
        $form->password = '12345';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testShortUsername(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'ab';
        $form->email = 'test@example.com';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('username'));
    }

    public function testValidData(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'testuser';
        $form->email = 'test@example.com';
        $form->password = 'secret123';
        $form->passwordRepeat = 'secret123';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    private function createForm(ModuleConfig $config = new ModuleConfig()): RegistrationForm
    {
        return new RegistrationForm($config, $this->createTranslator());
    }
    private function createTranslator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            #[\Override]
            public function addCategorySources(CategorySource ...$categories): static
            {
                return $this;
            }
            #[\Override]
            public function setLocale(string $locale): static
            {
                return $this;
            }
            #[\Override]
            public function getLocale(): string
            {
                return 'en';
            }
            #[\Override]
            public function translate(string|Stringable $id, array $parameters = [], ?string $category = null, ?string $locale = null): string
            {
                return (string) $id;
            }
            #[\Override]
            public function withDefaultCategory(string $category): static
            {
                return $this;
            }
            #[\Override]
            public function withLocale(string $locale): static
            {
                return $this;
            }
        };
    }
}
