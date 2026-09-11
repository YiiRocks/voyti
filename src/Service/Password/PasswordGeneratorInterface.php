<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

/**
 * Generates a random password string of a given length.
 */
interface PasswordGeneratorInterface
{
    /**
     * Generates a password that satisfies the configured password policy. The requested length is
     * adjusted when necessary to fit the policy's minimum and maximum lengths.
     */
    public function generate(int $length): string;
}
