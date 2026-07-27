<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Account;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `account/update` screen.
 */
final readonly class UpdateViewData
{
    private function __construct(
        public MenuViewData $menu,
        public string $formSubmitUrl,
    ) {}

    public static function create(
        ModuleConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
        bool $isSwitched,
        ?User $originalUser,
    ): self {
        return new self(
            menu: MenuViewData::forAccount($config, $url, $translator, $isSwitched, $originalUser),
            formSubmitUrl: $url->generate('voyti/user-account'),
        );
    }
}
