<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use DateTimeImmutable;

/**
 * Computes a person's age in whole years from a birthday.
 */
final class AgeHelper
{
    public static function calculate(?DateTimeImmutable $birthday, ?DateTimeImmutable $now = null): ?int
    {
        if ($birthday === null) {
            return null;
        }

        $now ??= new DateTimeImmutable();
        if ($birthday > $now) {
            return null;
        }

        return $birthday->diff($now)->y;
    }
}
