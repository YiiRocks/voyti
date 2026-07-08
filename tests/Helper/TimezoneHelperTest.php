<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\TimezoneHelper;

final class TimezoneHelperTest extends TestCase
{

    public function testGetAllContainsUtc(): void
    {
        $timezones = TimezoneHelper::getAll();
        self::assertArrayHasKey('UTC', $timezones);
        self::assertStringContainsString('UTC', $timezones['UTC']);
        self::assertStringContainsString('GMT', $timezones['UTC']);
    }

    public function testGetAllKeysAreTimezoneIdentifiers(): void
    {
        $timezones = TimezoneHelper::getAll();
        foreach ($timezones as $key => $value) {
            self::assertTrue(in_array($key, \DateTimeZone::listIdentifiers(), true), "{$key} is not a valid timezone");
        }
    }
    public function testGetAllReturnsArray(): void
    {
        $timezones = TimezoneHelper::getAll();
        self::assertIsArray($timezones);
        self::assertNotEmpty($timezones);
    }

    public function testGetAllReturnsStringValues(): void
    {
        $timezones = TimezoneHelper::getAll();
        foreach ($timezones as $key => $value) {
            self::assertIsString($key);
            self::assertIsString($value);
        }
    }

    public function testGetAllSortedAlphabetically(): void
    {
        $timezones = TimezoneHelper::getAll();
        $sorted = $timezones;
        asort($sorted);
        self::assertSame($sorted, $timezones);
    }

    public function testGetAllValuesFormattedCorrectly(): void
    {
        $timezones = TimezoneHelper::getAll();
        foreach ($timezones as $key => $value) {
            self::assertStringStartsWith('(GMT', $value);
            self::assertStringEndsWith($key, $value);
        }
    }

    public function testIsValidWithEmptyString(): void
    {
        self::assertFalse(TimezoneHelper::isValid(''));
    }

    public function testIsValidWithInvalidTimezone(): void
    {
        self::assertFalse(TimezoneHelper::isValid('Invalid/Timezone'));
        self::assertFalse(TimezoneHelper::isValid(''));
    }

    public function testIsValidWithValidTimezone(): void
    {
        self::assertTrue(TimezoneHelper::isValid('UTC'));
        self::assertTrue(TimezoneHelper::isValid('America/New_York'));
        self::assertTrue(TimezoneHelper::isValid('Europe/London'));
    }
}
