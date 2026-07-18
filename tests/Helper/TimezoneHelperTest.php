<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\TimezoneHelper;

final class TimezoneHelperTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function isValidProvider(): iterable
    {
        yield 'empty string' => ['', false];
        yield 'invalid timezone' => ['Invalid/Timezone', false];
        yield 'UTC' => ['UTC', true];
        yield 'America/New_York' => ['America/New_York', true];
        yield 'Europe/London' => ['Europe/London', true];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function localizedDateFormatProvider(): iterable
    {
        yield 'Dutch' => ['nl', '14 nov 2023'];
        yield 'German' => ['de', '14.11.2023'];
        yield 'Russian' => ['ru', '14 нояб'];
        yield 'Spanish' => ['es', '14 nov 2023'];
    }

    #[DataProvider('localizedDateFormatProvider')]
    public function testFormatLocalizedUsesLocaleDateFormat(string $locale, string $expectedStart): void
    {
        $formatted = TimezoneHelper::formatLocalized(1700000000, $locale);
        self::assertStringStartsWith($expectedStart, $formatted);
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

    public function testFormatLocalizedWithTimezoneShiftsDisplayedTime(): void
    {
        $formatted = TimezoneHelper::formatLocalized(1700000000, 'en', 'America/New_York');
        self::assertStringStartsWith('Nov 14, 2023, 5:13:20', $formatted);
        self::assertMatchesRegularExpression('/PM$/u', $formatted);
    }

    public function testGetAllFormatsNegativeHalfHourOffsetCorrectly(): void
    {
        $timezones = TimezoneHelper::getAll();

        self::assertArrayHasKey('Pacific/Marquesas', $timezones);
        self::assertStringStartsWith('(GMT-9:30)', $timezones['Pacific/Marquesas']);
    }

    public function testGetAllFormatsPositiveHourOffsetCorrectly(): void
    {
        $timezones = TimezoneHelper::getAll();

        self::assertArrayHasKey('Africa/Lagos', $timezones);
        self::assertStringStartsWith('(GMT+1:00)', $timezones['Africa/Lagos']);
    }

    public function testGetAllReturnsWellFormedTimezoneList(): void
    {
        $timezones = TimezoneHelper::getAll();

        self::assertIsArray($timezones);
        self::assertNotEmpty($timezones);

        self::assertArrayHasKey('UTC', $timezones);
        self::assertStringContainsString('UTC', $timezones['UTC']);
        self::assertStringContainsString('GMT', $timezones['UTC']);

        $sorted = $timezones;
        asort($sorted);
        self::assertSame($sorted, $timezones);

        foreach ($timezones as $key => $value) {
            self::assertTrue(in_array($key, \DateTimeZone::listIdentifiers(), true), "{$key} is not a valid timezone");
            self::assertIsString($key);
            self::assertIsString($value);
            self::assertStringStartsWith('(GMT', $value);
            self::assertStringEndsWith($key, $value);
        }
    }

    #[DataProvider('isValidProvider')]
    public function testIsValid(string $timezone, bool $expected): void
    {
        self::assertSame($expected, TimezoneHelper::isValid($timezone));
    }
}
