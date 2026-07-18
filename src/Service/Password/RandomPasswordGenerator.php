<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

use Yiisoft\Security\Random;

/**
 * Default {@see PasswordGeneratorInterface} implementation backed by {@see Random::string()}.
 */
final class RandomPasswordGenerator implements PasswordGeneratorInterface
{
    #[\Override]
    public function generate(int $length): string
    {
        return Random::string($length);
    }
}
