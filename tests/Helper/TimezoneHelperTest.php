<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Helper;

use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\Helper\TimezoneHelper;

final class TimezoneHelperTest extends TestCase
{
    public function testGetAllReturnsArray(): void
    {
        $timezones = TimezoneHelper::getAll();
        self::assertIsArray($timezones);
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

    public function testGetAllKeysAreValidTimezoneIdentifiers(): void
    {
        $timezones = TimezoneHelper::getAll();
        foreach (array_keys($timezones) as $timezone) {
            self::assertContains($timezone, \DateTimeZone::listIdentifiers());
        }
    }

    public function testGetAllContainsUtc(): void
    {
        $timezones = TimezoneHelper::getAll();
        self::assertArrayHasKey('UTC', $timezones);
    }

    public function testGetAllContainsAmericaNewYork(): void
    {
        $timezones = TimezoneHelper::getAll();
        self::assertArrayHasKey('America/New_York', $timezones);
    }

    public function testGetAllContainsEuropeLondon(): void
    {
        $timezones = TimezoneHelper::getAll();
        self::assertArrayHasKey('Europe/London', $timezones);
    }

    public function testGetAllContainsAsiaTokyo(): void
    {
        $timezones = TimezoneHelper::getAll();
        self::assertArrayHasKey('Asia/Tokyo', $timezones);
    }

    public function testGetAllValuesContainGmtOffset(): void
    {
        $timezones = TimezoneHelper::getAll();
        foreach ($timezones as $timezone => $label) {
            self::assertStringContainsString('GMT', $label);
            self::assertStringContainsString($timezone, $label);
        }
    }

    public function testGetAllIsSortedByLabel(): void
    {
        $timezones = TimezoneHelper::getAll();
        $values = array_values($timezones);
        $sorted = $values;
        sort($sorted);
        self::assertSame($sorted, $values);
    }

    public function testIsValidReturnsTrueForValidTimezone(): void
    {
        self::assertTrue(TimezoneHelper::isValid('UTC'));
        self::assertTrue(TimezoneHelper::isValid('America/New_York'));
        self::assertTrue(TimezoneHelper::isValid('Europe/Berlin'));
    }

    public function testIsValidReturnsFalseForInvalidTimezone(): void
    {
        self::assertFalse(TimezoneHelper::isValid('Invalid/Timezone'));
        self::assertFalse(TimezoneHelper::isValid(''));
    }

    public function testGetAllContainsExactGmtOffsetForUtc(): void
    {
        $timezones = TimezoneHelper::getAll();
        $this->assertStringContainsString('GMT+0:00', $timezones['UTC']);
    }

    public function testGetAllContainsExactGmtOffsets(): void
    {
        $timezones = TimezoneHelper::getAll();
        foreach ($timezones as $timezone => $label) {
            $this->assertMatchesRegularExpression('/GMT[+-]\d+:\d{2}/', $label);
        }
    }

    public function testGetAllGmtOffsetForAsiaKolkata(): void
    {
        $timezones = TimezoneHelper::getAll();
        $this->assertStringContainsString('GMT+5:30', $timezones['Asia/Kolkata']);
    }

    public function testGetAllGmtOffsetForAsiaTokyo(): void
    {
        $timezones = TimezoneHelper::getAll();
        $this->assertStringContainsString('GMT+9:00', $timezones['Asia/Tokyo']);
    }

    public function testGetAllGmtOffsetForPacificHonolulu(): void
    {
        $timezones = TimezoneHelper::getAll();
        $this->assertStringContainsString('GMT-10:00', $timezones['Pacific/Honolulu']);
    }
}
