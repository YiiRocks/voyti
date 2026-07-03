<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Stringable;
use YiiRocks\Voyti\Form\Auth\ResendForm;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Validator;

final class ResendFormTest extends TestCase
{

    public function testDefaults(): void
    {
        $form = $this->createForm();
        $this->assertSame('', $form->email);
        $this->assertSame('', $form->gRecaptchaResponse);
    }

    public function testEmptyEmail(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = '';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('email'));
    }

    public function testGetAttributeLabels(): void
    {
        $form = $this->createForm();
        $labels = $form->getAttributeLabels();

        $this->assertArrayHasKey('email', $labels);
        $this->assertSame('voyti.view.email_label', $labels['email']);
    }

    public function testGetFormName(): void
    {
        $form = $this->createForm();
        $this->assertSame('resend', $form->getFormName());
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
        $this->assertSame('voyti_resend', $gRecaptchaResponse[0]->getAction());
        $this->assertSame(0.5, $gRecaptchaResponse[0]->getThreshold());
    }

    public function testInvalidEmail(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = 'not-an-email';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('email'));
    }

    public function testPropertyAccess(): void
    {
        $form = $this->createForm();
        $form->email = 'test@example.com';
        $form->gRecaptchaResponse = 'test-userToken';

        $this->assertSame('test@example.com', $form->getPropertyValue('email'));
        $this->assertSame('test-userToken', $form->getPropertyValue('gRecaptchaResponse'));
    }

    public function testValidData(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->email = 'user@example.com';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    private function createForm(ModuleConfig $config = new ModuleConfig()): ResendForm
    {
        return new ResendForm($config, $this->createTranslator());
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
