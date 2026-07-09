<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

final class ApiTokenHasher
{
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
