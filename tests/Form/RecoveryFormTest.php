<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Stringable;
use YiiRocks\Voyti\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\CompareType;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Equal;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Validator;

final class RecoveryFormTest extends TestCase
{

    public function testDefaults(): void
    {
        $form = $this->createForm();

        $this->assertSame('', $form->email);
        $this->assertSame('', $form->password);
        $this->assertSame('', $form->passwordRepeat);
        $this->assertSame('', $form->gRecaptchaResponse);
        $this->assertSame(RecoveryForm::SCENARIO_REQUEST, $form->scenario);
    }

    public function testEmailAtMaxLengthBoundaryPasses(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = str_repeat('a', 243) . '@example.com';
        $this->assertSame(255, strlen($form->email));

        $result = $validator->validate($form);
        $this->assertTrue($result->isPropertyValid('email'));
    }

    public function testEmailOverMaxLengthBoundaryFails(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = str_repeat('a', 244) . '@example.com';
        $this->assertSame(256, strlen($form->email));

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('email'));
    }

    public function testEmptyAll(): void
    {
        $validator = new Validator();
        $form = $this->createForm();

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('email'));
        $this->assertTrue($result->isPropertyValid('password'));
    }

    public function testEmptyPassword(): void
    {
        $validator = new Validator();
        $form = $this->createForm(scenario: RecoveryForm::SCENARIO_RESET);
        $form->password = '';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testGetAttributeLabels(): void
    {
        $form = $this->createForm();
        $labels = $form->getAttributeLabels();

        $this->assertArrayHasKey('email', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('passwordRepeat', $labels);
        $this->assertSame('voyti.view.email_label', $labels['email']);
        $this->assertSame('voyti.view.new_password_label', $labels['password']);
        $this->assertSame('voyti.view.new_password_repeat_label', $labels['passwordRepeat']);
    }

    public function testGetFormName(): void
    {
        $form = $this->createForm();
        $this->assertSame('recovery', $form->getFormName());
    }

    public function testGetPropertyLabelForKnownProperty(): void
    {
        $form = $this->createForm();

        $this->assertSame('voyti.view.email_label', $form->getPropertyLabel('email'));
        $this->assertSame('voyti.view.new_password_label', $form->getPropertyLabel('password'));
    }

    public function testGetPropertyLabelForUnknownPropertyFallsBackToParent(): void
    {
        $form = $this->createForm();

        $this->assertSame('G Recaptcha Response', $form->getPropertyLabel('gRecaptchaResponse'));
    }

    public function testGetRulesWithRequestScenario(): void
    {
        $form = $this->createForm(scenario: RecoveryForm::SCENARIO_REQUEST);
        $rules = $form->getRules();
        /** @var array<string, mixed> $rules */
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayNotHasKey('password', $rules);
        /** @var list<object> $emailRules */
        $emailRules = $rules['email'];
        $this->assertCount(3, $emailRules);
        $this->assertInstanceOf(Required::class, $emailRules[0]);
        $this->assertInstanceOf(Email::class, $emailRules[1]);
        $this->assertTrue($emailRules[1]->shouldCheckDns());
        $this->assertTrue($emailRules[1]->isIdnEnabled());
        $this->assertTrue($emailRules[1]->getSkipOnEmpty());
        $this->assertInstanceOf(Length::class, $emailRules[2]);
        $this->assertSame(255, $emailRules[2]->getMax());
    }

    public function testGetRulesWithRequestScenarioAndRecaptchaV2(): void
    {
        $form = $this->createForm(
            config: new ModuleConfig(recaptchaVersion: 'v2'),
            scenario: RecoveryForm::SCENARIO_REQUEST,
        );
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

    public function testGetRulesWithRequestScenarioAndRecaptchaV3(): void
    {
        $form = $this->createForm(
            config: new ModuleConfig(recaptchaVersion: 'v3'),
            scenario: RecoveryForm::SCENARIO_REQUEST,
        );
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
        $this->assertSame('voyti_recovery', $gRecaptchaResponse[0]->getAction());
        $this->assertSame(0.5, $gRecaptchaResponse[0]->getThreshold());
    }

    public function testGetRulesWithResetScenario(): void
    {
        $form = $this->createForm(scenario: RecoveryForm::SCENARIO_RESET);
        $rules = $form->getRules();
        /** @var array<string, mixed> $rules */
        $this->assertArrayHasKey('password', $rules);
        $this->assertArrayHasKey('passwordRepeat', $rules);
        $this->assertArrayNotHasKey('email', $rules);
        $this->assertArrayNotHasKey('gRecaptchaResponse', $rules);
        /** @var list<object> $passwordRules */
        $passwordRules = $rules['password'];
        $this->assertCount(2, $passwordRules);
        $this->assertInstanceOf(Required::class, $passwordRules[0]);
        $this->assertInstanceOf(Length::class, $passwordRules[1]);
        $this->assertSame(6, $passwordRules[1]->getMin());
        $this->assertSame(72, $passwordRules[1]->getMax());
        /** @var list<object> $passwordRepeatRules */
        $passwordRepeatRules = $rules['passwordRepeat'];
        $this->assertCount(2, $passwordRepeatRules);
        $this->assertInstanceOf(Required::class, $passwordRepeatRules[0]);
        $this->assertInstanceOf(Equal::class, $passwordRepeatRules[1]);
        $this->assertSame('password', $passwordRepeatRules[1]->getTargetProperty());
        $this->assertSame('===', $passwordRepeatRules[1]->getOperator());
        $this->assertSame(CompareType::STRING, $passwordRepeatRules[1]->getType());
    }

    public function testInvalidEmail(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = 'not-email';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('email'));
        $this->assertTrue($result->isPropertyValid('password'));
    }

    public function testLongEmailFails(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = str_repeat('a', 256) . '@example.com';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('email'));
    }

    public function testLongPassword(): void
    {
        $validator = new Validator();
        $form = $this->createForm(scenario: RecoveryForm::SCENARIO_RESET);
        $form->password = str_repeat('a', 73);

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testPasswordRepeatMismatchFails(): void
    {
        $validator = new Validator();
        $form = $this->createForm(scenario: RecoveryForm::SCENARIO_RESET);
        $form->password = 'newsecret';
        $form->passwordRepeat = 'different';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('passwordRepeat'));
    }

    public function testPropertyAccess(): void
    {
        $form = $this->createForm();
        $form->email = 'user@example.com';
        $form->password = 'secret123';
        $form->gRecaptchaResponse = 'test-userToken';

        $this->assertSame('user@example.com', $form->getPropertyValue('email'));
        $this->assertSame('secret123', $form->getPropertyValue('password'));
        $this->assertSame('test-userToken', $form->getPropertyValue('gRecaptchaResponse'));
    }

    public function testResetScenarioAcceptsBoundaryPasswordLengths(): void
    {
        $validator = new Validator();

        $minForm = $this->createForm(scenario: RecoveryForm::SCENARIO_RESET);
        $minForm->password = '123456';
        $minForm->passwordRepeat = '123456';
        $minResult = $validator->validate($minForm);
        $this->assertTrue($minResult->isValid());

        $maxForm = $this->createForm(scenario: RecoveryForm::SCENARIO_RESET);
        $maxForm->password = str_repeat('a', 72);
        $maxForm->passwordRepeat = str_repeat('a', 72);
        $maxResult = $validator->validate($maxForm);
        $this->assertTrue($maxResult->isValid());
    }

    public function testResetScenarioDefaults(): void
    {
        $form = $this->createForm(scenario: RecoveryForm::SCENARIO_RESET);

        $this->assertSame('', $form->gRecaptchaResponse);
        $this->assertSame(RecoveryForm::SCENARIO_RESET, $form->scenario);
    }

    public function testScenariosConstants(): void
    {
        $this->assertSame('request', RecoveryForm::SCENARIO_REQUEST);
        $this->assertSame('reset', RecoveryForm::SCENARIO_RESET);
    }

    public function testShortPassword(): void
    {
        $validator = new Validator();
        $form = $this->createForm(scenario: RecoveryForm::SCENARIO_RESET);
        $form->password = '12345';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testValidRequestData(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = 'user@example.com';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    public function testValidResetData(): void
    {
        $validator = new Validator();
        $form = $this->createForm(scenario: RecoveryForm::SCENARIO_RESET);
        $form->password = 'newsecret';
        $form->passwordRepeat = 'newsecret';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    private function createForm(ModuleConfig $config = new ModuleConfig(), string $scenario = RecoveryForm::SCENARIO_REQUEST): RecoveryForm
    {
        return new RecoveryForm($config, $this->createTranslator(), scenario: $scenario);
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
