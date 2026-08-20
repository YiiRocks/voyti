<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper;

use Composer\InstalledVersions;
use RuntimeException;
use YiiRocks\Voyti\Controller\RenderTrait;

/**
 * Locates the installed package providing the `yiirocks/voyti-views` virtual package (e.g.
 * `yiirocks/voyti-views-bootstrap5`), by convention named `yiirocks/voyti-views-*`. This lets
 * {@see RenderTrait} and every module that ships bundled views
 * (voyti-gdpr, voyti-social-auth, voyti-2fa and its method packages) resolve the same shared
 * views directory without depending on a specific implementation, so a host can swap
 * `voyti-views-bootstrap5` for an alternative theme package.
 */
final class ViewsPackageHelper
{
    public static function viewsPath(): string
    {
        $found = [];
        foreach (InstalledVersions::getInstalledPackages() as $package) {
            if (!str_starts_with($package, 'yiirocks/voyti-views-')) {
                continue;
            }

            $path = InstalledVersions::getInstallPath($package);
            if ($path !== null) {
                $found[$package] = $path;
            }
        }

        if (count($found) > 1) {
            throw new RuntimeException(sprintf(
                'Multiple packages provide "yiirocks/voyti-views": %s. Only one views package may be '
                . 'installed at a time.',
                implode(', ', array_keys($found)),
            ));
        }

        if ($found !== []) {
            return reset($found) . '/views';
        }

        // @codeCoverageIgnoreStart
        // Defensive fallback: voyti's own composer.json requires the virtual "yiirocks/voyti-views"
        // package, so Composer itself guarantees a concrete package satisfying it is installed
        // whenever this code runs; unreachable in practice, kept only to satisfy the return type.
        throw new RuntimeException(
            'No package providing "yiirocks/voyti-views" is installed. Require a views package, '
            . 'e.g. "yiirocks/voyti-views-bootstrap5".',
        );
        // @codeCoverageIgnoreEnd
    }
}
