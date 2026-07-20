<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Privacy;

use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Data for the `privacy/anonymize` screen.
 */
final readonly class AnonymizeViewData
{
    private function __construct(
        public string $formSubmitUrl,
    ) {}

    public static function create(UrlGeneratorInterface $url): self
    {
        return new self(formSubmitUrl: $url->generate('voyti/privacy-anonymize'));
    }
}
