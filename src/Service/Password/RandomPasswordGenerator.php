<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Password;

use Yiisoft\Security\Random;

final class RandomPasswordGenerator implements PasswordGeneratorInterface
{
    #[\Override]
    public function generate(int $length): string
    {
        return Random::string($length);
    }
}
