<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\tests\Support;

use YiiRocks\Voyti\VoytiConfig;

/**
 * Builds a {@see VoytiConfig} from the package's real `config/params.php` defaults, with
 * per-test overrides layered on top — avoids duplicating the default values here.
 */
final class VoytiConfigFactory
{
    public static function create(mixed ...$overrides): VoytiConfig
    {
        return new VoytiConfig(...[...self::defaults(), ...$overrides]);
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
