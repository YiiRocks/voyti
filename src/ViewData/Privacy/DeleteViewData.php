<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Privacy;

use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Data for the `privacy/delete` screen.
 */
final readonly class DeleteViewData
{
    private function __construct(
        public string $formSubmitUrl,
    ) {}

    public static function create(UrlGeneratorInterface $url): self
    {
        return new self(formSubmitUrl: $url->generate('voyti/user-privacy-delete'));
    }
}
