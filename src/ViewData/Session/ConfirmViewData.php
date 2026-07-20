<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Session;

use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Data for the `session/confirm` (two-factor login confirmation) screen.
 */
final readonly class ConfirmViewData
{
    private function __construct(
        public string $method,
        public string $formSubmitUrl,
    ) {}

    public static function create(string $method, UrlGeneratorInterface $url): self
    {
        return new self(
            method: $method,
            formSubmitUrl: $url->generate('voyti/session-confirm'),
        );
    }
}
