<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Settings;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\Form\Settings\SettingsForm;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\tests\Support\ModuleConfigFactory;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;
use Yiisoft\Validator\Rule\Regex;

#[AllowMockObjectsWithoutExpectations]
final class SettingsFormTest extends TestCase
{
    use TranslatorMockTrait;

    public function testConstruct(): void
    {
        $form = new SettingsForm(ModuleConfigFactory::create(), $this->createTranslator());
        $this->assertSame('', $form->email);
        $this->assertSame('', $form->username);
        $this->assertSame('', $form->password);
        $this->assertSame('', $form->passwordRepeat);
        $this->assertNull($form->getUser());
    }

    public function testGetFormName(): void
    {
        $form = new SettingsForm(ModuleConfigFactory::create(), $this->createTranslator());
        $this->assertSame('settings', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new SettingsForm(ModuleConfigFactory::create(), $this->createTranslator());
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('username', $labels);
        $this->assertArrayHasKey('email', $labels);
        $this->assertArrayHasKey('password', $labels);
        $this->assertArrayHasKey('passwordRepeat', $labels);
        $this->assertArrayHasKey('publicEmail', $labels);
        $this->assertArrayHasKey('name', $labels);
        $this->assertArrayHasKey('bio', $labels);
        $this->assertArrayHasKey('currentPassword', $labels);
        $this->assertArrayHasKey('authTfEnabled', $labels);
    }

    public function testGetRulesWithPasswordComplexityDisabled(): void
    {
        $form = new SettingsForm(ModuleConfigFactory::create(), $this->createTranslator());
        $rules = $form->getRules();
        $this->assertCount(1, $rules['password']);
    }

    public function testGetRulesWithPasswordComplexityEnabled(): void
    {
        $config = ModuleConfigFactory::create(enablePasswordComplexity: true);
        $form = new SettingsForm($config, $this->createTranslator());
        $rules = $form->getRules();
        $this->assertCount(2, $rules['password']);
        $this->assertInstanceOf(Regex::class, $rules['password'][1]);
    }

    public function testGetUserReturnsNullByDefault(): void
    {
        $form = new SettingsForm(ModuleConfigFactory::create(), $this->createTranslator());
        $this->assertNull($form->getUser());
    }

    public function testGetValidationPropertyLabels(): void
    {
        $form = new SettingsForm(ModuleConfigFactory::create(), $this->createTranslator());
        $this->assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
    }

    public function testSetAndGetUser(): void
    {
        $form = new SettingsForm(ModuleConfigFactory::create(), $this->createTranslator());
        $user = new User();
        $form->setUser($user);
        $this->assertSame($user, $form->getUser());
    }

    public function testSetProperties(): void
    {
        $form = new SettingsForm(ModuleConfigFactory::create(), $this->createTranslator());
        $form->email = 'new@example.com';
        $form->username = 'newuser';
        $form->password = 'newpass123';
        $form->passwordRepeat = 'newpass123';

        $this->assertSame('new@example.com', $form->email);
        $this->assertSame('newuser', $form->username);
        $this->assertSame('newpass123', $form->password);
        $this->assertSame('newpass123', $form->passwordRepeat);
    }
}
