<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form\Settings;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Form\Settings\UserProfileForm;
use Yiisoft\Translator\TranslatorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class UserProfileFormTest extends TestCase
{

    public function testConstruct(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $this->assertSame('', $form->name);
        $this->assertSame('', $form->publicEmail);
        $this->assertSame('', $form->gravatarEmail);
        $this->assertSame('', $form->location);
        $this->assertSame('', $form->website);
        $this->assertSame('', $form->timezone);
        $this->assertSame('', $form->bio);
    }

    public function testGetAttributeLabels(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $labels = $form->getAttributeLabels();
        $this->assertArrayHasKey('name', $labels);
        $this->assertArrayHasKey('publicEmail', $labels);
        $this->assertArrayHasKey('gravatarEmail', $labels);
        $this->assertArrayHasKey('location', $labels);
        $this->assertArrayHasKey('website', $labels);
        $this->assertArrayHasKey('timezone', $labels);
        $this->assertArrayHasKey('bio', $labels);
    }

    public function testGetFormName(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $this->assertSame('userProfile', $form->getFormName());
    }

    public function testGetPropertyLabels(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $this->assertSame($form->getAttributeLabels(), $form->getPropertyLabels());
    }

    public function testSetProperties(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $form->name = 'John Doe';
        $form->publicEmail = 'public@example.com';
        $form->gravatarEmail = 'gravatar@example.com';
        $form->location = 'New York';
        $form->website = 'https://example.com';
        $form->timezone = 'America/New_York';
        $form->bio = 'A brief bio';

        $this->assertSame('John Doe', $form->name);
        $this->assertSame('public@example.com', $form->publicEmail);
        $this->assertSame('gravatar@example.com', $form->gravatarEmail);
        $this->assertSame('New York', $form->location);
        $this->assertSame('https://example.com', $form->website);
        $this->assertSame('America/New_York', $form->timezone);
        $this->assertSame('A brief bio', $form->bio);
    }

    public function testValidateTimezoneWithEmptyString(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $result = $form->validateTimezone('');
        $this->assertFalse($result->isValid());
    }

    public function testValidateTimezoneWithInvalidTimezone(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $result = $form->validateTimezone('Invalid/Timezone');
        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function testValidateTimezoneWithValidTimezone(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $result = $form->validateTimezone('UTC');
        $this->assertTrue($result->isValid());
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
