<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use YiiRocks\Voyti\ModuleConfig;

/**
 * Builds a {@see ModuleConfig} from the package's real `config/params.php` defaults, with
 * per-test overrides layered on top — mirrors how a host application's DI container
 * constructs it, without duplicating the default values here.
 */
final class ModuleConfigFactory
{
    public static function create(mixed ...$overrides): ModuleConfig
    {
        return new ModuleConfig(...[...self::defaults(), ...$overrides]);
    }

    /**
     * @psalm-suppress MixedArgument, UnresolvableInclude
     */
    private static function defaults(): array
    {
        $params = require dirname(__DIR__, 2) . '/config/params.php';

        return $params['yiirocks/voyti'];
    }
}
