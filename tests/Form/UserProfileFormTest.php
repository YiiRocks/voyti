<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Form;

use YiiRocks\Voyti\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\tests\TestCase;

final class UserProfileFormTest extends TestCase
{
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
}
