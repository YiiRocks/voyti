<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Stringable;
use YiiRocks\Voyti\Form\Auth\LoginForm;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Validator;

final class LoginFormTest extends TestCase
{

    public function testDefaults(): void
    {
        $form = $this->createForm();

        $this->assertSame('', $form->login);
        $this->assertSame('', $form->password);
        $this->assertFalse($form->rememberMe);
        $this->assertNull($form->twoFactorAuthenticationCode);
        $this->assertSame('', $form->gRecaptchaResponse);
    }

    public function testEmptyAll(): void
    {
        $validator = new Validator();
        $form = $this->createForm();

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
    }

    public function testEmptyLogin(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->login = '';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('login'));
    }

    public function testEmptyPassword(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->login = 'testuser';
        $form->password = '';

        $result = $validator->validate($form);
        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testGetRulesWithRecaptchaEnabled(): void
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
    }

    public function testOptionalFields(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->login = 'testuser';
        $form->password = 'secret123';
        $form->rememberMe = true;
        $form->twoFactorAuthenticationCode = '123456';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    public function testPropertyAccess(): void
    {
        $form = $this->createForm();
        $form->login = 'testuser';
        $form->password = 'secret123';
        $form->rememberMe = true;
        $form->twoFactorAuthenticationCode = '654321';
        $form->gRecaptchaResponse = 'test-token';

        $this->assertSame('testuser', $form->getPropertyValue('login'));
        $this->assertSame('secret123', $form->getPropertyValue('password'));
        $this->assertTrue($form->getPropertyValue('rememberMe'));
        $this->assertSame('654321', $form->getPropertyValue('twoFactorAuthenticationCode'));
        $this->assertSame('test-token', $form->getPropertyValue('gRecaptchaResponse'));
    }

    public function testPropertyLabels(): void
    {
        $form = $this->createForm();
        $labels = $form->getPropertyLabels();

        $this->assertEmpty($labels);
    }

    public function testValidData(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->login = 'testuser';
        $form->password = 'secret123';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    private function createForm(ModuleConfig $config = new ModuleConfig()): LoginForm
    {
        return new LoginForm($config, $this->createTranslator());
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
