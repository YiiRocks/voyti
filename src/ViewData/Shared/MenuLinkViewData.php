<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Shared;

/**
 * A single navigation link in a {@see MenuViewData} menu.
 */
final readonly class MenuLinkViewData
{
    /**
     * @param bool $alignEnd renders this link separated to the far end of the menu (used for logout)
     */
    public function __construct(
        public string $label,
        public string $url,
        public bool $alignEnd = false,
    ) {}
}
