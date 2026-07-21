<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Settings;

use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `settings/index` screen.
 */
final readonly class IndexViewData
{
    private function __construct(
        public MenuViewData $menu,
        public string $displayName,
        public string $email,
        public string $memberSinceDisplay,
    ) {}

    public static function create(
        ModuleConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
        User $user,
    ): self {
        $profile = $user->getProfile();

        return new self(
            menu: MenuViewData::forAccount($config, $url, $translator),
            displayName: $profile?->getName() ?? $user->getUsername(),
            email: $user->getEmail(),
            memberSinceDisplay: TimezoneHelper::formatLocalized(
                $user->getCreatedAt(),
                $translator->getLocale(),
                $profile?->getTimezone(),
            ),
        );
    }
}
