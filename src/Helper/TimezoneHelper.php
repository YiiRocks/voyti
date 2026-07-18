<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Throwable;

/**
 * Timezone utilities: locale-aware timestamp formatting, listing all identifiers with a
 * human-readable GMT offset label, and validating a timezone identifier.
 */
final class TimezoneHelper
{
    public static function formatLocalized(int $timestamp, string $locale, ?string $timezone = null): string
    {
        if ($timezone !== null && !self::isValid($timezone)) {
            $timezone = null;
        }

        try {
            /**
             * Depending on the PHP/ICU version, an invalid locale either
             * throws from the constructor or leaves the formatter
             * "unconstructed" and throws only once format() is called
             * (see https://github.com/php/php-src/issues/12561), so both
             * calls need to be inside the try.
             */
            $formatted = (new IntlDateFormatter(
                $locale,
                IntlDateFormatter::MEDIUM,
                IntlDateFormatter::MEDIUM,
                $timezone,
            ))->format($timestamp);
        } catch (Throwable) {
            return date(DATE_RFC1123, $timestamp);
        }

        return $formatted !== false ? $formatted : date(DATE_RFC1123, $timestamp);
    }

    /**
     * @return string[]
     *
     * @psalm-return array<string, string>
     */
    public static function getAll(): array
    {
        $now = new DateTimeImmutable();
        $timezones = [];
        foreach (DateTimeZone::listIdentifiers() as $timezone) {
            /** @var non-empty-string $timezone */
            $offset = (new DateTimeZone($timezone))->getOffset($now);
            /**
             * @infection-ignore-all Real timezone offsets are multiples of 900, so 3599 divisor
             * is behaviourally equivalent to 3600 for every entry in the list.
             */
            $hours = intdiv($offset, 3600);
            /** @infection-ignore-all Same domain-level equivalence as above for the modulo and minute divisors. */
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
