<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Auth;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiRocks\Recaptcha\RecaptchaV2Rule;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Model\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\TrueValue;

#[AllowMockObjectsWithoutExpectations]
final class RegistrationFormTest extends TestCase
{
    use TranslatorMockTrait;

    public static function passwordComplexityProvider(): array
    {
        return [
            'disabled' => [false, 2],
            'enabled' => [true, 3],
        ];
    }

    public function testGetPropertyLabels(): void
    {
        $form = new RegistrationForm(VoytiConfigFactory::create(), $this->createTranslator());
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('username', $labels);
        $this->assertArrayHasKey('email', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('passwordRepeat', $labels);
        $this->assertArrayHasKey('dataProcessingConsent', $labels);
    }

    public function testGetRulesRequiresDataProcessingConsent(): void
    {
        $config = VoytiConfigFactory::create();
        $form = new RegistrationForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('dataProcessingConsent', $rules);
        $rule = $rules['dataProcessingConsent'][0];
        $this->assertInstanceOf(TrueValue::class, $rule);
        $this->assertTrue($rule->getTrueValue());
    }

    #[DataProvider('passwordComplexityProvider')]
    public function testGetRulesWithPasswordComplexity(bool $enabled, int $expectedCount): void
    {
        $config = VoytiConfigFactory::create(enablePasswordComplexity: $enabled);
        $form = new RegistrationForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertCount($expectedCount, $rules['password']);
        if ($enabled) {
            $this->assertInstanceOf(Regex::class, $rules['password'][2]);
        }
    }

    public function testGetRulesWithRecaptchaV2(): void
    {
        $config = VoytiConfigFactory::create(recaptchaVersion: RecaptchaVersion::V2);
        $form = new RegistrationForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $this->assertInstanceOf(RecaptchaV2Rule::class, $rules['gRecaptchaResponse'][0]);
    }
}
