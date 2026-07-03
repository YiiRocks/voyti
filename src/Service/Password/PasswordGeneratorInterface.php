<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

interface PasswordGeneratorInterface
{
    public function generate(int $length): string;
}
