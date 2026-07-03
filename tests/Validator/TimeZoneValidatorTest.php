<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Validator;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Validator\TimeZoneValidator;

final class TimeZoneValidatorTest extends TestCase
{

    public function testMultipleInstancesAreIndependent(): void
    {
        $valid = new TimeZoneValidator('UTC');
        $invalid = new TimeZoneValidator('Fake/Zone');

        self::assertTrue($valid->validate());
        self::assertFalse($invalid->validate());
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

    public function testValidateWithAllValidTimezones(): void
    {
        $validTimezones = \DateTimeZone::listIdentifiers();
        foreach ($validTimezones as $timezone) {
            $validator = new TimeZoneValidator($timezone);
            self::assertTrue($validator->validate(), "Expected '{$timezone}' to be valid.");
        }
    }

    public function testValidateWithUtcOffsetString(): void
    {
        $validator = new TimeZoneValidator('+05:30');
        self::assertFalse($validator->validate());
    }
}
