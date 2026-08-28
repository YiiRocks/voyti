<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Auth;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiRocks\Recaptcha\RecaptchaV2Rule;
use YiiRocks\Recaptcha\RecaptchaV3Rule;
use YiiRocks\Voyti\Enum\RecaptchaVersion;
use YiiRocks\Voyti\Model\Form\Auth\ResendForm;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use YiiRocks\Voyti\tests\Support\VoytiConfigFactory;

#[AllowMockObjectsWithoutExpectations]
final class ResendFormTest extends TestCase
{
    use TranslatorMockTrait;

    public static function recaptchaProvider(): array
    {
        return [
            'v2' => [RecaptchaVersion::V2, RecaptchaV2Rule::class],
            'v3' => [RecaptchaVersion::V3, RecaptchaV3Rule::class],
        ];
    }

    public function testGetPropertyLabels(): void
    {
        $form = new ResendForm(VoytiConfigFactory::create(), $this->createTranslator());
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('email', $labels);
    }

    #[DataProvider('recaptchaProvider')]
    public function testGetRules(RecaptchaVersion $version, string $ruleClass): void
    {
        $config = VoytiConfigFactory::create(recaptchaVersion: $version);
        $form = new ResendForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('gRecaptchaResponse', $rules);
        $rule = $rules['gRecaptchaResponse'][0];
        $this->assertInstanceOf($ruleClass, $rule);
        if ($version === RecaptchaVersion::V3) {
            $this->assertSame(0.5, $rule->getThreshold());
            $this->assertSame('voyti_resend', $rule->getAction());
        }
    }

    public function testGetValidationPropertyLabels(): void
    {
        $form = new ResendForm(VoytiConfigFactory::create(), $this->createTranslator());
        $this->assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
    }
}
