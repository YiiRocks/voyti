<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Stringable;
use YiiRocks\Voyti\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Validator;

final class RecoveryFormTest extends TestCase
{

    public function testDefaults(): void
    {
        $form = $this->createForm();

        $this->assertSame('', $form->email);
        $this->assertSame('', $form->password);
        $this->assertSame('', $form->gRecaptchaResponse);
        $this->assertSame(RecoveryForm::SCENARIO_REQUEST, $form->scenario);
    }

    public function testEmptyAll(): void
    {
        $validator = new Validator();
        $form = $this->createForm();

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
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

    public function testGetAttributeLabels(): void
    {
        $form = $this->createForm();
        $labels = $form->getAttributeLabels();

        $this->assertArrayHasKey('email', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertSame('voyti.view.email_label', $labels['email']);
        $this->assertSame('voyti.view.new_password_label', $labels['password']);
    }

    public function testGetFormName(): void
    {
        $form = $this->createForm();
        $this->assertSame('recovery', $form->getFormName());
    }

    public function testGetRulesWithRequestScenario(): void
    {
        $form = $this->createForm(scenario: RecoveryForm::SCENARIO_REQUEST);
        $rules = $form->getRules();
        /** @var array<string, mixed> $rules */
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
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
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertArrayNotHasKey('gRecaptchaResponse', $rules);
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
        $form = $this->createForm();
        $form->email = 'user@example.com';
        $form->password = '12345';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('password'));
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
