<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form\Settings;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Form\Settings\SettingsForm;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class SettingsFormTest extends TestCase
{

    public function testConstruct(): void
    {
        $form = new SettingsForm($this->createTranslator());
        $this->assertSame('', $form->email);
        $this->assertSame('', $form->username);
        $this->assertSame('', $form->password);
        $this->assertSame('', $form->passwordRepeat);
        $this->assertNull($form->getUser());
    }

    public function testGetAttributeLabels(): void
    {
        $form = new SettingsForm($this->createTranslator());
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
    }

    public function testGetFormName(): void
    {
        $form = new SettingsForm($this->createTranslator());
        $this->assertSame('settings', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new SettingsForm($this->createTranslator());
        $this->assertSame($form->getAttributeLabels(), $form->getPropertyLabels());
    }

    public function testGetUserReturnsNullByDefault(): void
    {
        $form = new SettingsForm($this->createTranslator());
        $this->assertNull($form->getUser());
    }

    public function testSetAndGetUser(): void
    {
        $form = new SettingsForm($this->createTranslator());
        $user = new User();
        $form->setUser($user);
        $this->assertSame($user, $form->getUser());
    }

    public function testSetProperties(): void
    {
        $form = new SettingsForm($this->createTranslator());
        $form->email = 'new@example.com';
        $form->username = 'newuser';
        $form->password = 'newpass123';
        $form->passwordRepeat = 'newpass123';

        $this->assertSame('new@example.com', $form->email);
        $this->assertSame('newuser', $form->username);
        $this->assertSame('newpass123', $form->password);
        $this->assertSame('newpass123', $form->passwordRepeat);
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
