<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Profile;

use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use YiiRocks\Voyti\ViewData\Shared\ProfileCardViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `profile/update` screen.
 */
final readonly class UpdateViewData
{
    /**
     * @param array<string, string> $timezoneOptions
     * @param string $updateUrl POST target for the main profile-update form
     */
    private function __construct(
        public MenuViewData $menu,
        public string $updateUrl,
        public ProfileCardViewData $profile,
        public array $timezoneOptions,
    ) {}

    public static function create(
        User $user,
        UserProfile $userProfile,
        ModuleConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
        bool $isSwitched,
        ?User $originalUser,
    ): self {
        return new self(
            menu: MenuViewData::forAccount($config, $url, $translator, $isSwitched, $originalUser),
            updateUrl: $url->generate('voyti/user-profile'),
            profile: ProfileCardViewData::create(
                $user,
                $userProfile,
                $translator,
                profilePreviewClass: 'list-group list-group-flush',
            ),
            timezoneOptions: TimezoneHelper::getAll(),
        );
    }
}
