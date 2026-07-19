<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Model\Form\Settings;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Model\Form\Settings\UserProfileForm;
use YiiRocks\Voyti\tests\Support\TranslatorMockTrait;

#[AllowMockObjectsWithoutExpectations]
final class UserProfileFormTest extends TestCase
{
    use TranslatorMockTrait;

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
        $this->assertSame('', $form->birthday);
    }

    public function testGetFormName(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $this->assertSame('userProfile', $form->getFormName());
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

    public function testGetValidationPropertyLabels(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $this->assertSame($form->getPropertyLabels(), $form->getValidationPropertyLabels());
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
        $form->birthday = '1990-05-15';

        $this->assertSame('John Doe', $form->name);
        $this->assertSame('public@example.com', $form->publicEmail);
        $this->assertSame('gravatar@example.com', $form->gravatarEmail);
        $this->assertSame('New York', $form->location);
        $this->assertSame('https://example.com', $form->website);
        $this->assertSame('America/New_York', $form->timezone);
        $this->assertSame('A brief bio', $form->bio);
        $this->assertSame('1990-05-15', $form->birthday);
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

    public function testValidateBirthdayNotInFutureWithToday(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $today = (new DateTimeImmutable())->format('Y-m-d');
        $result = $form->validateBirthdayNotInFuture($today);
        $this->assertTrue($result->isValid());
    }

    public function testValidateBirthdayNotInFutureWithUnparseableString(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $result = $form->validateBirthdayNotInFuture('not-a-date');
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

    public function testValidateNoHtmlTagsWithPlainText(): void
    {
        $form = new UserProfileForm($this->createTranslator());
        $result = $form->validateNoHtmlTags('A brief bio');
        $this->assertTrue($result->isValid());
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
}
