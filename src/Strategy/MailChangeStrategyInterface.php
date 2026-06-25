<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

interface MailChangeStrategyInterface
{
    public const int TYPE_DEFAULT = 1;
    public const int TYPE_INSECURE = 0;
    public const int TYPE_SECURE = 2;

    public function run(): bool;
}
