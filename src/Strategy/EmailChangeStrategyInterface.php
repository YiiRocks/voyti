<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Strategy;

interface EmailChangeStrategyInterface
{
    public function run(): bool;
}
