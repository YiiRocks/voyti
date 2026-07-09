<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\TimezoneHelper;

final class TimezoneHelperTest extends TestCase
{

    public function testFormatLocalizedWithDutchLocaleUsesDutchDateFormat(): void
    {
        $formatted = TimezoneHelper::formatLocalized(1700000000, 'nl');
        self::assertStringStartsWith('14 nov 2023', $formatted);
        self::assertStringEndsWith('22:13:20', $formatted);
    }

    public function testFormatLocalizedWithEnglishLocaleUsesEnglishDateFormat(): void
    {
        $formatted = TimezoneHelper::formatLocalized(1700000000, 'en');
        self::assertStringStartsWith('Nov 14, 2023', $formatted);
        self::assertMatchesRegularExpression('/[AP]M$/u', $formatted);
    }

    public function testFormatLocalizedWithGermanLocaleDiffersFromEnglishLocale(): void
    {
        $german = TimezoneHelper::formatLocalized(1700000000, 'de');
        $english = TimezoneHelper::formatLocalized(1700000000, 'en');
        self::assertNotSame($german, $english);
    }

    public function testFormatLocalizedWithGermanLocaleUsesGermanDateFormat(): void
    {
        $formatted = TimezoneHelper::formatLocalized(1700000000, 'de');
        self::assertStringStartsWith('14.11.2023', $formatted);
        self::assertStringEndsWith('22:13:20', $formatted);
    }

    public function testFormatLocalizedWithInvalidLocaleFallsBackToRfc1123(): void
    {
        $timestamp = 1700000000;
        self::assertSame(date(DATE_RFC1123, $timestamp), TimezoneHelper::formatLocalized($timestamp, 'not-a-locale'));
    }

    public function testFormatLocalizedWithInvalidTimezoneIsIgnored(): void
    {
        $timestamp = 1700000000;
        $withInvalidTimezone = TimezoneHelper::formatLocalized($timestamp, 'en', 'Invalid/Timezone');
        $withoutTimezone = TimezoneHelper::formatLocalized($timestamp, 'en');
        self::assertSame($withoutTimezone, $withInvalidTimezone);
    }

    public function testFormatLocalizedWithRussianLocaleUsesRussianDateFormat(): void
    {
        $formatted = TimezoneHelper::formatLocalized(1700000000, 'ru');
        self::assertStringStartsWith('14 нояб', $formatted);
        self::assertStringEndsWith('22:13:20', $formatted);
    }

    public function testFormatLocalizedWithSpanishLocaleUsesSpanishDateFormat(): void
    {
        $formatted = TimezoneHelper::formatLocalized(1700000000, 'es');
        self::assertStringStartsWith('14 nov 2023', $formatted);
        self::assertStringEndsWith('22:13:20', $formatted);
    }

    public function testFormatLocalizedWithTimezoneShiftsDisplayedTime(): void
    {
        $formatted = TimezoneHelper::formatLocalized(1700000000, 'en', 'America/New_York');
        self::assertStringStartsWith('Nov 14, 2023, 5:13:20', $formatted);
        self::assertMatchesRegularExpression('/PM$/u', $formatted);
    }

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
