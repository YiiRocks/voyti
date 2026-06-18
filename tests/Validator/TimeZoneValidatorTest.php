<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Validator\TimeZoneValidator;

final class TimeZoneValidatorTest extends TestCase
{
    public function testValidateWithValidUtcTimezone(): void
    {
        $validator = new TimeZoneValidator('UTC');
        self::assertTrue($validator->validate());
    }

    public function testValidateWithValidContinentalTimezone(): void
    {
        $validator = new TimeZoneValidator('Europe/London');
        self::assertTrue($validator->validate());
    }

    public function testValidateWithValidAmericanTimezone(): void
    {
        $validator = new TimeZoneValidator('America/New_York');
        self::assertTrue($validator->validate());
    }

    public function testValidateWithValidAsianTimezone(): void
    {
        $validator = new TimeZoneValidator('Asia/Tokyo');
        self::assertTrue($validator->validate());
    }

    public function testValidateWithValidPacificTimezone(): void
    {
        $validator = new TimeZoneValidator('Pacific/Auckland');
        self::assertTrue($validator->validate());
    }

    public function testValidateWithValidAustralianTimezone(): void
    {
        $validator = new TimeZoneValidator('Australia/Sydney');
        self::assertTrue($validator->validate());
    }

    public function testValidateFailsWithEmptyString(): void
    {
        $validator = new TimeZoneValidator('');
        self::assertFalse($validator->validate());
    }

    public function testValidateFailsWithGibberish(): void
    {
        $validator = new TimeZoneValidator('Not/A/Real/Timezone');
        self::assertFalse($validator->validate());
    }

    public function testValidateFailsWithNumericString(): void
    {
        $validator = new TimeZoneValidator('12345');
        self::assertFalse($validator->validate());
    }

    public function testValidateFailsWithPartialValidName(): void
    {
        $validator = new TimeZoneValidator('Europe');
        self::assertFalse($validator->validate());
    }

    public function testValidateWithUtcOffsetString(): void
    {
        $validator = new TimeZoneValidator('+05:30');
        self::assertFalse($validator->validate());
    }

    public function testValidateWithAllValidTimezones(): void
    {
        $validTimezones = \DateTimeZone::listIdentifiers();
        foreach ($validTimezones as $timezone) {
            $validator = new TimeZoneValidator($timezone);
            self::assertTrue($validator->validate(), "Expected '{$timezone}' to be valid.");
        }
    }

    public function testMultipleInstancesAreIndependent(): void
    {
        $valid = new TimeZoneValidator('UTC');
        $invalid = new TimeZoneValidator('Fake/Zone');

        self::assertTrue($valid->validate());
        self::assertFalse($invalid->validate());
    }
}
