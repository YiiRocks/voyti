<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\Dashboard;

/**
 * A single stat tile on the admin dashboard.
 */
final readonly class DashboardTile
{
    /**
     * @param string $labelKey a raw translation message key - pass to `$translator->translate()`
     *        yourself, unlike most other ViewData string fields which are already translated
     * @param string $borderClass a Bootstrap `border-*` class for the tile's accent color
     */
    public function __construct(
        public string $labelKey,
        public int $value,
        public string $url,
        public string $borderClass,
    ) {}
}
