<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\TimezoneHelper;

final class TimezoneHelperTest extends TestCase
{

    public function testGetAllContainsAmericaNewYork(): void
    {
        $timezones = TimezoneHelper::getAll();
        self::assertArrayHasKey('America/New_York', $timezones);
    }

    public function testGetAllContainsExactGmtOffsetForUtc(): void
    {
        $timezones = TimezoneHelper::getAll();
        $this->assertStringContainsString('GMT+0:00', (string) $timezones['UTC']);
    }

    public function testGetAllContainsExactGmtOffsets(): void
    {
        $timezones = TimezoneHelper::getAll();
        foreach ($timezones as $timezone => $label) {
            $this->assertMatchesRegularExpression('/GMT[+-]\d+:\d{2}/', (string) $label);
        }
    }

    public function testGetAllGmtOffsetForAsiaKolkata(): void
    {
        $timezones = TimezoneHelper::getAll();
        $this->assertStringContainsString('GMT+5:30', (string) $timezones['Asia/Kolkata']);
    }

    public function testGetAllGmtOffsetForAsiaTokyo(): void
    {
        $timezones = TimezoneHelper::getAll();
        $this->assertStringContainsString('GMT+9:00', (string) $timezones['Asia/Tokyo']);
    }

    public function testGetAllGmtOffsetForPacificHonolulu(): void
    {
        $timezones = TimezoneHelper::getAll();
        $this->assertStringContainsString('GMT-10:00', (string) $timezones['Pacific/Honolulu']);
    }

    public function testGetAllIsSortedByLabel(): void
    {
        $timezones = TimezoneHelper::getAll();
        $values = array_values($timezones);
        $sorted = $values;
        sort($sorted);
        self::assertSame($sorted, $values);
    }

    public function testGetAllKeysAreValidTimezoneIdentifiers(): void
    {
        $timezones = TimezoneHelper::getAll();
        foreach (array_keys($timezones) as $timezone) {
            self::assertContains($timezone, \DateTimeZone::listIdentifiers());
        }
    }
    public function testGetAllReturnsNonEmptyArray(): void
    {
        $timezones = TimezoneHelper::getAll();
        self::assertNotEmpty($timezones);
    }

    public function testGetAllReturnsStringKeys(): void
    {
        $timezones = TimezoneHelper::getAll();
        foreach ($timezones as $key => $value) {
            self::assertIsString($key);
            self::assertIsString($value);
        }
    }

    public function testGetAllValuesContainGmtOffset(): void
    {
        $timezones = TimezoneHelper::getAll();
        foreach ($timezones as $timezone => $label) {
            self::assertStringContainsString((string) $timezone, (string) $label);
        }
    }

    public function testIsValidReturnsFalseForInvalidTimezone(): void
    {
        self::assertFalse(TimezoneHelper::isValid('Invalid/Timezone'));
    }

    public function testIsValidReturnsTrueForValidTimezone(): void
    {
        self::assertTrue(TimezoneHelper::isValid('UTC'));
    }
}
