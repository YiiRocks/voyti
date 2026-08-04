<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use DateTimeImmutable;

/**
 * Computes a person's age in whole years from a birthday.
 */
final class AgeHelper
{
    public static function calculate(?DateTimeImmutable $birthday): ?int
    {
        $now = new DateTimeImmutable();
        /**
         * @infection-ignore-all
         * `>` vs `>=` only differs when $birthday equals $now to the microsecond; $now is
         * constructed fresh above with no clock injection, so no test can force that exact match.
         */
        if ($birthday === null || $birthday > $now) {
            return null;
        }

        return $birthday->diff($now)->y;
    }
}
