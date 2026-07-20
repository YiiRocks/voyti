<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\SocialNetwork;

/**
 * A single connected social account row on the `social-network/index` screen.
 */
final readonly class SocialAccountRow
{
    public function __construct(
        public string $providerTitle,
        public string $formSubmitUrl,
    ) {}
}
