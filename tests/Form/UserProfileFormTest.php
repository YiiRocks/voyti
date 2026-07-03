<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use YiiRocks\Voyti\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\tests\TestCase;
use Yiisoft\Validator\Validator;

final class UserProfileFormTest extends TestCase
{
    public function testAllFieldsAreOptional(): void
    {
        $validator = new Validator();
        $form = new UserProfileForm($this->getTranslator());

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }

    public function testGetAttributeLabelsReturnsAllTranslatedLabels(): void
    {
        $form = new UserProfileForm($this->getTranslator());

        self::assertSame(
            [
                'name' => 'Name',
                'publicEmail' => 'Public email',
                'gravatarEmail' => 'Gravatar email',
                'location' => 'Location',
                'website' => 'Website',
                'timezone' => 'Timezone',
                'bio' => 'Bio',
            ],
            $form->getAttributeLabels(),
        );
    }

    public function testGetFormNameReturnsUserProfile(): void
    {
        $form = new UserProfileForm($this->getTranslator());

        self::assertSame('userProfile', $form->getFormName());
    }

    public function testGetPropertyLabelFallsBackToParentForUnknownProperty(): void
    {
        $form = new UserProfileForm($this->getTranslator());

        // "unknownProperty" is not part of getAttributeLabels(), so the
        // parent FormModel generates a title-cased label from the property
        // name instead of returning a translated attribute label.
        self::assertSame('Unknown Property', $form->getPropertyLabel('unknownProperty'));
    }

    public function testGetPropertyLabelForMappedPropertyDiffersFromParentGeneratedLabel(): void
    {
        $form = new UserProfileForm($this->getTranslator());

        // "publicEmail" is a mapped attribute whose translated label is
        // "Public email". If the mapped return statement were removed, the
        // call would instead fall through to the parent's generated label
        // "Public Email" (title case), which differs from the translation.
        self::assertSame('Public email', $form->getPropertyLabel('publicEmail'));
    }

    public function testGetPropertyLabelReturnsMappedLabelForEachKnownProperty(): void
    {
        $form = new UserProfileForm($this->getTranslator());

        self::assertSame('Name', $form->getPropertyLabel('name'));
        self::assertSame('Gravatar email', $form->getPropertyLabel('gravatarEmail'));
        self::assertSame('Location', $form->getPropertyLabel('location'));
        self::assertSame('Website', $form->getPropertyLabel('website'));
        self::assertSame('Timezone', $form->getPropertyLabel('timezone'));
        self::assertSame('Bio', $form->getPropertyLabel('bio'));
    }

    public function testInvalidGravatarEmailFails(): void
    {
        $validator = new Validator();
        $form = new UserProfileForm($this->getTranslator());
        $form->gravatarEmail = 'not-an-email';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('gravatarEmail'));
    }

    public function testInvalidPublicEmailFails(): void
    {
        $validator = new Validator();
        $form = new UserProfileForm($this->getTranslator());
        $form->publicEmail = 'not-an-email';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('publicEmail'));
    }

    public function testInvalidTimezoneFails(): void
    {
        $validator = new Validator();
        $form = new UserProfileForm($this->getTranslator());
        $form->timezone = 'Not/A/Real/Timezone';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('timezone'));
    }

    public function testInvalidWebsiteFails(): void
    {
        $validator = new Validator();
        $form = new UserProfileForm($this->getTranslator());
        $form->website = 'not a url';

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('website'));
    }

    public function testLongBioFails(): void
    {
        $validator = new Validator();
        $form = new UserProfileForm($this->getTranslator());
        $form->bio = str_repeat('a', 65536);

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('bio'));
    }

    public function testLongLocationFails(): void
    {
        $validator = new Validator();
        $form = new UserProfileForm($this->getTranslator());
        $form->location = str_repeat('a', 256);

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('location'));
    }

    public function testLongNameFails(): void
    {
        $validator = new Validator();
        $form = new UserProfileForm($this->getTranslator());
        $form->name = str_repeat('a', 256);

        $result = $validator->validate($form);
        $this->assertFalse($result->isPropertyValid('name'));
    }

    public function testValidateTimezoneIsPubliclyCallable(): void
    {
        $form = new UserProfileForm($this->getTranslator());

        $this->assertTrue($form->validateTimezone('UTC')->isValid());
        $this->assertFalse($form->validateTimezone('Not/A/Real/Timezone')->isValid());
    }

    public function testValidDataPasses(): void
    {
        $validator = new Validator();
        $form = new UserProfileForm($this->getTranslator());
        $form->name = 'John Doe';
        $form->publicEmail = 'public@example.com';
        $form->gravatarEmail = 'gravatar@example.com';
        $form->location = 'Earth';
        $form->website = 'https://example.com';
        $form->timezone = 'UTC';
        $form->bio = 'Hello there';

        $result = $validator->validate($form);
        $this->assertTrue($result->isValid());
    }
}
