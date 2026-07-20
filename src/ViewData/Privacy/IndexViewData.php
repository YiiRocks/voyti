<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Privacy;

use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `privacy/index` screen.
 */
final readonly class IndexViewData
{
    /**
     * @param bool $showGdprLinks whether to render $gdprConsentUrl as a link (`ModuleConfig::$enableGdprCompliance`)
     * @param bool $showDeleteLink whether to render $deleteUrl as a link (`ModuleConfig::$allowAccountDelete`)
     */
    private function __construct(
        public MenuViewData $menu,
        public bool $showGdprLinks,
        public string $gdprConsentUrl,
        public string $exportUrl,
        public string $anonymizeUrl,
        public bool $showDeleteLink,
        public string $deleteUrl,
    ) {}

    public static function create(ModuleConfig $config, UrlGeneratorInterface $url, TranslatorInterface $translator): self
    {
        return new self(
            menu: MenuViewData::forAccount($config, $url, $translator),
            showGdprLinks: $config->enableGdprCompliance,
            gdprConsentUrl: $url->generate('voyti/privacy-gdpr-consent'),
            exportUrl: $url->generate('voyti/privacy-export'),
            anonymizeUrl: $url->generate('voyti/privacy-anonymize'),
            showDeleteLink: $config->allowAccountDelete,
            deleteUrl: $url->generate('voyti/privacy-delete'),
        );
    }
}
