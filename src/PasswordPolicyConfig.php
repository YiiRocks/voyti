<?php

declare(strict_types=1);

namespace YiiRocks\Voyti;

use InvalidArgumentException;

final readonly class PasswordPolicyConfig
{
    public function __construct(
        public int $minLength = 6,
        public int $maxLength = 72,
        public int $minUppercase = 0,
        public int $minLowercase = 0,
        public int $minDigits = 0,
        public int $minSymbols = 0,
    ) {
        if ($this->minLength < 1) {
            throw new InvalidArgumentException('Password policy "minLength" must be at least 1.');
        }
        if ($this->maxLength < $this->minLength) {
            throw new InvalidArgumentException('Password policy "maxLength" must be greater than or equal to "minLength".');
        }
        if ($this->minUppercase < 0 || $this->minLowercase < 0 || $this->minDigits < 0 || $this->minSymbols < 0) {
            throw new InvalidArgumentException('Password policy character minimums must not be negative.');
        }

        $requiredCharacters = $this->minUppercase + $this->minLowercase + $this->minDigits + $this->minSymbols;
        if ($requiredCharacters > $this->maxLength) {
            throw new InvalidArgumentException('Password policy character minimums must not exceed "maxLength".');
        }
    }
}
