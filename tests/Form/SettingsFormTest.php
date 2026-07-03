<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use PHPUnit\Framework\TestCase;
use Stringable;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Validator\Helper\ObjectParser;
use Yiisoft\Validator\Rule\CompareType;
use Yiisoft\Validator\Rule\Equal;
use Yiisoft\Validator\Validator;

final class SettingsFormTest extends TestCase
{

    public function testDefaults(): void
    {
        $form = $this->createForm();

        $this->assertSame('', $form->username);
        $this->assertSame('', $form->email);
        $this->assertSame('', $form->password);
        $this->assertSame('', $form->passwordRepeat);
    }

    public function testEmptyPasswordFails(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'newuser';
        $form->email = 'new@example.com';
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
        $this->assertArrayHasKey('publicEmail', $labels);
        $this->assertArrayHasKey('name', $labels);
        $this->assertArrayHasKey('bio', $labels);
        $this->assertArrayHasKey('currentPassword', $labels);
        $this->assertArrayHasKey('authTfEnabled', $labels);
        $this->assertSame('voyti.view.username_label', $labels['username']);
        $this->assertSame('voyti.view.email_label', $labels['email']);
        $this->assertSame('voyti.view.new_password_label', $labels['password']);
        $this->assertSame('voyti.view.new_password_repeat_label', $labels['passwordRepeat']);
        $this->assertSame('voyti.view.public_email_label', $labels['publicEmail']);
        $this->assertSame('voyti.view.name_label', $labels['name']);
        $this->assertSame('voyti.view.bio_label', $labels['bio']);
        $this->assertSame('voyti.view.current_password_label', $labels['currentPassword']);
        $this->assertSame('voyti.view.account.two_factor_title', $labels['authTfEnabled']);
    }

    public function testGetFormName(): void
    {
        $form = $this->createForm();
        $this->assertSame('settings', $form->getFormName());
    }

    public function testGetPropertyLabelFallsBackForUnknownProperty(): void
    {
        $form = $this->createForm();

        $this->assertSame('Unknown Property', $form->getPropertyLabel('unknownProperty'));
    }

    public function testGetPropertyLabelReturnsMappedLabel(): void
    {
        $form = $this->createForm();

        $this->assertSame('voyti.view.username_label', $form->getPropertyLabel('username'));
        $this->assertSame('voyti.view.new_password_label', $form->getPropertyLabel('password'));
    }

    public function testLongPasswordFails(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'newuser';
        $form->email = 'new@example.com';
        $form->password = str_repeat('a', 73);

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('password'));
    }

    public function testPasswordRepeatMismatchFails(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'newuser';
        $form->email = 'new@example.com';
        $form->password = 'newsecret';
        $form->passwordRepeat = 'different';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('passwordRepeat'));
    }

    public function testPasswordRepeatRuleIsStrictStringComparisonAgainstPassword(): void
    {
        $form = $this->createForm();
        $rules = (new ObjectParser($form))->getRules();
        /** @var array<string, list<object>> $rules */
        $this->assertArrayHasKey('passwordRepeat', $rules);
        $passwordRepeatRules = $rules['passwordRepeat'];
        $this->assertCount(1, $passwordRepeatRules);
        $this->assertInstanceOf(Equal::class, $passwordRepeatRules[0]);
        $this->assertSame('password', $passwordRepeatRules[0]->getTargetProperty());
        $this->assertSame('===', $passwordRepeatRules[0]->getOperator());
        $this->assertSame(CompareType::STRING, $passwordRepeatRules[0]->getType());
    }

    public function testPropertyAccess(): void
    {
        $form = $this->createForm();
        $form->username = 'newuser';
        $form->email = 'new@example.com';
        $form->password = 'newsecret';

        $this->assertSame('newuser', $form->getPropertyValue('username'));
        $this->assertSame('new@example.com', $form->getPropertyValue('email'));
        $this->assertSame('newsecret', $form->getPropertyValue('password'));
    }

    public function testShortPasswordFails(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'newuser';
        $form->email = 'new@example.com';
        $form->password = '12345';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('password'));
    }
    public function testValidData(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->username = 'newuser';
        $form->email = 'new@example.com';
        $form->password = 'newsecret';
        $form->passwordRepeat = 'newsecret';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    public function testValidWithEmptyUsernameAndEmail(): void
    {
        $validator = new Validator();
        $form = $this->createForm();
        $form->password = 'newsecret';
        $form->passwordRepeat = 'newsecret';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    private function createForm(): SettingsForm
    {
        return new SettingsForm($this->createTranslator());
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
