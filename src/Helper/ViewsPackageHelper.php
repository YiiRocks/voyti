<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use Composer\InstalledVersions;
use RuntimeException;
use YiiRocks\Voyti\Controller\RenderTrait;

/**
 * Locates the installed package providing views for {@see RenderTrait} and every module that
 * ships bundled views (voyti-gdpr, voyti-social-auth, voyti-2fa and its method packages). Any
 * vendor may publish one (e.g. `acme/voyti-views-tailwind`) as long as its package name's local
 * part starts with `voyti-views-`, so a host can swap `voyti-views-bootstrap5` for an alternative
 * theme package. Detection here only checks the name; a views package is expected to also declare
 * `"provide": {"yiirocks/voyti-views": "..."}`, but that isn't re-verified here.
 */
final class ViewsPackageHelper
{
    private static ?string $viewsPath = null;

    public static function viewsPath(): string
    {
        /** @infection-ignore-all AssignCoalesce: memoization only affects performance across repeated calls; resolveViewsPath() is deterministic, so it's unobservable within a single test run. */
        return self::$viewsPath ??= self::resolveViewsPath();
    }

    private static function resolveViewsPath(): string
    {
        $packages = array_filter(
            InstalledVersions::getInstalledPackages(),
            static fn(string $package): bool => str_starts_with(explode('/', $package)[1] ?? '', 'voyti-views-'),
        );

        foreach ($packages as $package) {
            $path = InstalledVersions::getInstallPath($package);
            if ($path !== null) {
                return $path . '/views';
            }
        }

        // @codeCoverageIgnoreStart
        // Reachable in production (Composer requirements can be manually avoided/overruled), but
        // not exercisable here: InstalledVersions::reload() only supplements the real
        // installed-packages data rather than suppressing it.
        throw new RuntimeException(
            'No views package is installed. Require a package named "<vendor>/voyti-views-*", '
            . 'e.g. "yiirocks/voyti-views-bootstrap5".',
        );
        // @codeCoverageIgnoreEnd
    }
}
