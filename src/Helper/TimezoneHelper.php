<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use DateTime;
use DateTimeZone;

final class TimezoneHelper
{
    /**
     * @return string[]
     *
     * @psalm-return array<string, string>
     */
    public static function getAll(): array
    {
        $timezones = [];
        foreach (DateTimeZone::listIdentifiers() as $timezone) {
            /** @var non-empty-string $timezone */
            $dateTime = new DateTime('now', new DateTimeZone($timezone));
            $offset = $dateTime->getOffset();
            $hours = intdiv($offset, 3600);
            $minutes = abs(intdiv($offset % 3600, 60));
            $gmtOffset = sprintf('GMT%+d:%02d', $hours, $minutes);
            $timezones[$timezone] = "({$gmtOffset}) {$timezone}";
        }
        asort($timezones);
        return $timezones;
    }

    public static function isValid(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }
}
