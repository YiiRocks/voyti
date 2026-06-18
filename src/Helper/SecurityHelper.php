<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use Yiisoft\Security\PasswordHasher;
use Yiisoft\Security\Random;

final class SecurityHelper
{
    public function __construct(
        private readonly PasswordHasher $passwordHasher = new PasswordHasher(),
    ) {
    }

    public function generateRandomString(int $length = 32): string
    {
        return Random::string($length);
    }

    public function hashPassword(string $password, int $cost = 10): string
    {
        return (new PasswordHasher(parameters: ['cost' => $cost]))->hash($password);
    }

    public function validatePassword(string $password, string $hash): bool
    {
        return $this->passwordHasher->validate($password, $hash);
    }
}
