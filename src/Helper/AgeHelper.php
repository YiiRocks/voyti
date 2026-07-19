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
        if ($birthday === null || $birthday > $now) {
            return null;
        }

        return $birthday->diff($now)->y;
    }
}
