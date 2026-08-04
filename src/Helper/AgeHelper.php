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
        if ($birthday === null) {
            return null;
        }

        $diff = $birthday->diff(new DateTimeImmutable());

        return $diff->invert === 1 ? null : $diff->y;
    }
}
