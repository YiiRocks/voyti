<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Auth;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Model\Form\Auth\RecoveryForm;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use Yiisoft\Validator\Rule\CompareType;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Equal;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

#[AllowMockObjectsWithoutExpectations]
final class RecoveryFormTest extends TestCase
{
    use TranslatorMockTrait;

    /**
     * @return iterable<string, array{string}>
     */
    public static function constructScenarioProvider(): iterable
    {
        yield 'request' => [RecoveryForm::SCENARIO_REQUEST];
        yield 'reset' => [RecoveryForm::SCENARIO_RESET];
    }

    public function testConstructDefaultScenario(): void
    {
        $form = new RecoveryForm(ModuleConfigFactory::create(), $this->createTranslator());
        $this->assertSame(RecoveryForm::SCENARIO_REQUEST, $form->scenario);
    }

    #[DataProvider('constructScenarioProvider')]
    public function testConstructWithExplicitScenario(string $scenario): void
    {
        $form = new RecoveryForm(ModuleConfigFactory::create(), $this->createTranslator(), $scenario);
        $this->assertSame($scenario, $form->scenario);
    }

    public function testEmailProperty(): void
    {
        $form = new RecoveryForm(ModuleConfigFactory::create(), $this->createTranslator());
        $form->email = 'test@example.com';
        $this->assertSame('test@example.com', $form->email);
    }

    public function testGetFormName(): void
    {
        $form = new RecoveryForm(ModuleConfigFactory::create(), $this->createTranslator());
        $this->assertSame('recovery', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new RecoveryForm(ModuleConfigFactory::create(), $this->createTranslator());
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('email', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('passwordRepeat', $labels);
    }

    public function testGetRulesForRequestScenario(): void
    {
        $form = new RecoveryForm(ModuleConfigFactory::create(), $this->createTranslator(), RecoveryForm::SCENARIO_REQUEST);
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
        $form = new RecoveryForm(ModuleConfigFactory::create(), $this->createTranslator(), RecoveryForm::SCENARIO_RESET);
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
        $config = ModuleConfigFactory::create(enablePasswordComplexity: true);
        $form = new RecoveryForm($config, $this->createTranslator(), RecoveryForm::SCENARIO_RESET);
        $rules = $form->getRules();
        $this->assertCount(3, $rules['password']);
        $this->assertInstanceOf(Regex::class, $rules['password'][2]);
    }

    public function testGetRulesWithoutRecaptchaOnReset(): void
    {
        $config = ModuleConfigFactory::create(recaptchaVersion: RecaptchaVersion::V3);
        $form = new RecoveryForm($config, $this->createTranslator(), RecoveryForm::SCENARIO_RESET);
        $rules = $form->getRules();
        $this->assertArrayNotHasKey('gRecaptchaResponse', $rules);
    }

    public function testGetRulesWithRecaptchaV2OnRequest(): void
    {
        $config = ModuleConfigFactory::create(recaptchaVersion: RecaptchaVersion::V2);
        $form = new RecoveryForm($config, $this->createTranslator(), RecoveryForm::SCENARIO_REQUEST);
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
    }

    public function testGetRulesWithRecaptchaV3OnRequest(): void
    {
        $config = ModuleConfigFactory::create(recaptchaVersion: RecaptchaVersion::V3);
        $form = new RecoveryForm($config, $this->createTranslator(), RecoveryForm::SCENARIO_REQUEST);
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertCount(1, $rules['gRecaptchaResponse']);
        $this->assertSame('voyti_recovery', $rules['gRecaptchaResponse'][0]->getAction());
        $this->assertSame(0.5, $rules['gRecaptchaResponse'][0]->getThreshold());
    }

    public function testGetValidationPropertyLabels(): void
    {
        $form = new RecoveryForm(ModuleConfigFactory::create(), $this->createTranslator());
        $this->assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
    }

    public function testPasswordProperty(): void
    {
        $form = new RecoveryForm(ModuleConfigFactory::create(), $this->createTranslator(), RecoveryForm::SCENARIO_RESET);
        $form->password = 'newpass';
        $form->passwordRepeat = 'newpass';
        $this->assertSame('newpass', $form->password);
        $this->assertSame('newpass', $form->passwordRepeat);
    }
}
