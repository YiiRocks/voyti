<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Settings;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Voyti\Model\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\tests\Support\DatabaseTestCase;
use Yiisoft\Translator\TranslatorInterface;

#[AllowMockObjectsWithoutExpectations]
final class UserProfileFormTest extends DatabaseTestCase
{
    public function testFromProfileMapsEveryFieldThrough(): void
    {
        $profile = new UserProfile();
        $profile->setName('John Doe');
        $profile->setPublicEmail('public@example.com');
        $profile->setGravatarEmail('gravatar@example.com');
        $profile->setLocation('New York');
        $profile->setWebsite('https://example.com');
        $profile->setTimezone('America/New_York');
        $profile->setBio('A brief bio');
        $profile->setBirthday(new DateTimeImmutable('1990-05-15'));

        $form = UserProfileForm::fromProfile($profile, $this->createTranslator());

        $this->assertSame('John Doe', $form->name);
        $this->assertSame('public@example.com', $form->publicEmail);
        $this->assertSame('gravatar@example.com', $form->gravatarEmail);
        $this->assertSame('New York', $form->location);
        $this->assertSame('https://example.com', $form->website);
        $this->assertSame('America/New_York', $form->timezone);
        $this->assertSame('A brief bio', $form->bio);
        $this->assertSame('1990-05-15', $form->birthday);
    }

    public function testGetPropertyHints(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('translate')
            ->with('voyti.view.bio_variables_hint', ['age' => '{age}', 'location' => '{location}'], 'voyti')
            ->willReturn('Use {age} and {location} placeholders.');

        $form = new UserProfileForm($translator);
        $hints = $form->getPropertyHints();
        $this->assertSame(['bio' => 'Use {age} and {location} placeholders.'], $hints);
    }

    public function testGetPropertyLabels(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $labels = $form->getPropertyLabels();
        $this->assertArrayHasKey('name', $labels);
        $this->assertArrayHasKey('publicEmail', $labels);
        $this->assertArrayHasKey('gravatarEmail', $labels);
        $this->assertArrayHasKey('location', $labels);
        $this->assertArrayHasKey('website', $labels);
        $this->assertArrayHasKey('timezone', $labels);
        $this->assertArrayHasKey('bio', $labels);
        $this->assertArrayHasKey('birthday', $labels);
    }

    public function testValidateBirthdayNotInFutureWithFutureDate(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $futureDate = (new DateTimeImmutable('+1 year'))->format('Y-m-d');
        $result = $form->validateBirthdayNotInFuture($futureDate);
        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function testValidateBirthdayNotInFutureWithPastDate(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $result = $form->validateBirthdayNotInFuture('1990-05-15');
        $this->assertTrue($result->isValid());
    }

    public function testValidateNoHtmlTagsWithEmptyString(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $result = $form->validateNoHtmlTags('');
        $this->assertTrue($result->isValid());
    }

    public function testValidateNoHtmlTagsWithHtmlTags(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $result = $form->validateNoHtmlTags('<script>alert(1)</script>');
        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function testValidateNoHtmlTagsWithNonStringValue(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $result = $form->validateNoHtmlTags(123);
        $this->assertTrue($result->isValid());
    }

    public function testValidateTimezoneWithEmptyString(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $result = $form->validateTimezone('');
        $this->assertFalse($result->isValid());
    }
}
