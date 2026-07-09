<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form\Auth;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Rule\CompareType;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Equal;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class RecoveryFormTest extends TestCase
{

    public function testConstructDefaultScenario(): void
    {
        $form = new RecoveryForm(new ModuleConfig(), $this->createTranslator());
        $this->assertSame(RecoveryForm::SCENARIO_REQUEST, $form->scenario);
    }

    public function testConstructRequestScenario(): void
    {
        $form = new RecoveryForm(new ModuleConfig(), $this->createTranslator(), RecoveryForm::SCENARIO_REQUEST);
        $this->assertSame(RecoveryForm::SCENARIO_REQUEST, $form->scenario);
    }

    public function testConstructResetScenario(): void
    {
        $form = new RecoveryForm(new ModuleConfig(), $this->createTranslator(), RecoveryForm::SCENARIO_RESET);
        $this->assertSame(RecoveryForm::SCENARIO_RESET, $form->scenario);
    }

    public function testEmailProperty(): void
    {
        $form = new RecoveryForm(new ModuleConfig(), $this->createTranslator());
        $form->email = 'test@example.com';
        $this->assertSame('test@example.com', $form->email);
    }

    public function testGetAttributeLabels(): void
    {
        $form = new RecoveryForm(new ModuleConfig(), $this->createTranslator());
        $labels = $form->getAttributeLabels();
        $this->assertArrayHasKey('email', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('passwordRepeat', $labels);
    }

    public function testGetFormName(): void
    {
        $form = new RecoveryForm(new ModuleConfig(), $this->createTranslator());
        $this->assertSame('recovery', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new RecoveryForm(new ModuleConfig(), $this->createTranslator());
        $this->assertSame($form->getAttributeLabels(), $form->getPropertyLabels());
    }

    public function testGetRulesForRequestScenario(): void
    {
        $form = new RecoveryForm(new ModuleConfig(), $this->createTranslator(), RecoveryForm::SCENARIO_REQUEST);
        $rules = $form->getRules();
        $this->assertArrayHasKey('email', $rules);
        $this->assertCount(3, $rules['email']);
        $this->assertInstanceOf(Required::class, $rules['email'][0]);
        $this->assertInstanceOf(Email::class, $rules['email'][1]);
        $this->assertTrue($rules['email'][1]->shouldCheckDns());
        $this->assertTrue($rules['email'][1]->isIdnEnabled());
        $this->assertTrue($rules['email'][1]->getSkipOnEmpty());
        $this->assertInstanceOf(Length::class, $rules['email'][2]);
        $this->assertSame(255, $rules['email'][2]->getMax());
        $this->assertArrayNotHasKey('password', $rules);
        $this->assertArrayNotHasKey('passwordRepeat', $rules);
    }

    public function testGetRulesForResetScenario(): void
    {
        $form = new RecoveryForm(new ModuleConfig(), $this->createTranslator(), RecoveryForm::SCENARIO_RESET);
        $rules = $form->getRules();
        $this->assertArrayNotHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertCount(2, $rules['password']);
        $this->assertInstanceOf(Required::class, $rules['password'][0]);
        $this->assertInstanceOf(Length::class, $rules['password'][1]);
        $this->assertSame(6, $rules['password'][1]->getMin());
        $this->assertSame(72, $rules['password'][1]->getMax());
        $this->assertArrayHasKey('passwordRepeat', $rules);
        $this->assertCount(2, $rules['passwordRepeat']);
        $this->assertInstanceOf(Required::class, $rules['passwordRepeat'][0]);
        $this->assertInstanceOf(Equal::class, $rules['passwordRepeat'][1]);
        $this->assertSame('password', $rules['passwordRepeat'][1]->getTargetProperty());
        $this->assertSame(CompareType::STRING, $rules['passwordRepeat'][1]->getType());
        $this->assertSame('===', $rules['passwordRepeat'][1]->getOperator());
    }

    public function testGetRulesForResetScenarioWithPasswordComplexityEnabled(): void
    {
        $config = new ModuleConfig(enablePasswordComplexity: true);
        $form = new RecoveryForm($config, $this->createTranslator(), RecoveryForm::SCENARIO_RESET);
        $rules = $form->getRules();
        $this->assertCount(3, $rules['password']);
        $this->assertInstanceOf(\Yiisoft\Validator\Rule\Regex::class, $rules['password'][2]);
    }

    public function testGetRulesWithoutRecaptchaOnReset(): void
    {
        $config = new ModuleConfig(recaptchaVersion: 'v3');
        $form = new RecoveryForm($config, $this->createTranslator(), RecoveryForm::SCENARIO_RESET);
        $rules = $form->getRules();
        $this->assertArrayNotHasKey('gRecaptchaResponse', $rules);
    }

    public function testGetRulesWithRecaptchaV2OnRequest(): void
    {
        $config = new ModuleConfig(recaptchaVersion: 'v2');
        $form = new RecoveryForm($config, $this->createTranslator(), RecoveryForm::SCENARIO_REQUEST);
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
    }

    public function testGetRulesWithRecaptchaV3OnRequest(): void
    {
        $config = new ModuleConfig(recaptchaVersion: 'v3');
        $form = new RecoveryForm($config, $this->createTranslator(), RecoveryForm::SCENARIO_REQUEST);
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertCount(1, $rules['gRecaptchaResponse']);
        $this->assertSame('voyti_recovery', $rules['gRecaptchaResponse'][0]->getAction());
        $this->assertSame(0.5, $rules['gRecaptchaResponse'][0]->getThreshold());
    }

    public function testPasswordProperty(): void
    {
        $form = new RecoveryForm(new ModuleConfig(), $this->createTranslator(), RecoveryForm::SCENARIO_RESET);
        $form->password = 'newpass';
        $form->passwordRepeat = 'newpass';
        $this->assertSame('newpass', $form->password);
        $this->assertSame('newpass', $form->passwordRepeat);
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
