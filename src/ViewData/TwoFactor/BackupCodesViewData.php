<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\TwoFactor;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use YiiRocks\Voyti\VoytiConfig;
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
    public static function create(
        array $codes,
        VoytiConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
        bool $isSwitched,
        ?User $originalUser,
    ): self {
        return new self(
            menu: MenuViewData::forAccount($config, $url, $translator, $isSwitched, $originalUser),
            codes: $codes,
            continueUrl: $url->generate('voyti/user-two-factor'),
        );
    }
}
