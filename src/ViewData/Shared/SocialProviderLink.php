<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Shared;

/**
 * A single "sign in/connect with X" button in a {@see SocialConnectViewData} list.
 */
final readonly class SocialProviderLink
{
    public function __construct(
        public string $title,
        public string $url,
    ) {}
}
