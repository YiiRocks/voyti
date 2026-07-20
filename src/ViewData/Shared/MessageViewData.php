<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Shared;

/**
 * Data for the generic `shared/message` screen used to show a single message with a "go home" link.
 */
final readonly class MessageViewData
{
    public function __construct(
        public string $title,
        public string $homeUrl,
    ) {}
}
