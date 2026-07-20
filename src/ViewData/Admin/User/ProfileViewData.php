<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\User;

use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `admin/user/_profile` screen.
 */
final readonly class ProfileViewData
{
    /**
     * @param array<string, string> $timezoneOptions
     */
    private function __construct(
        public MenuViewData $menu,
        public string $formSubmitUrl,
        public array $timezoneOptions,
    ) {}

    public static function create(User $user, UrlGeneratorInterface $url, TranslatorInterface $translator): self
    {
        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            formSubmitUrl: $url->generate('voyti/admin-users-update-profile', ['id' => $user->getId()]),
            timezoneOptions: TimezoneHelper::getAll(),
        );
    }
}
