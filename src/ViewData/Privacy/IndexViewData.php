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
            gdprConsentUrl: $config->enableGdprCompliance ? $url->generate('voyti/user-privacy-gdpr-consent') : '',
            exportUrl: $config->enableGdprCompliance ? $url->generate('voyti/user-privacy-export') : '',
            anonymizeUrl: $config->enableGdprCompliance ? $url->generate('voyti/user-privacy-anonymize') : '',
            showDeleteLink: $config->allowAccountDelete,
            deleteUrl: $config->allowAccountDelete ? $url->generate('voyti/user-privacy-delete') : '',
        );
    }
}
