<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\TwoFactor;

use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `two-factor/backup-codes` screen.
 */
final readonly class BackupCodesViewData
{
    /**
     * @param list<string> $codes
     */
    private function __construct(
        public MenuViewData $menu,
        public array $codes,
        public string $continueUrl,
    ) {}

    /**
     * @param list<string> $codes
     */
    public static function create(array $codes, ModuleConfig $config, UrlGeneratorInterface $url, TranslatorInterface $translator): self
    {
        return new self(
            menu: MenuViewData::forAccount($config, $url, $translator),
            codes: $codes,
            continueUrl: $url->generate('voyti/two-factor'),
        );
    }
}
