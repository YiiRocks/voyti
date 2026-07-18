<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

/**
 * Generates a random password string of a given length.
 */
interface PasswordGeneratorInterface
{
    public function generate(int $length): string;
}
