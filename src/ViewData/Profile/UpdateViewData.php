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
     * @param string|null $switchedBannerMessage set only when an admin is currently impersonating
     *        another user; pair with $switchIdentityRestoreUrl to offer a "restore my identity" action
     * @param string $switchIdentityRestoreUrl POST target for a separate small form restoring the
     *        admin's original identity - not the main profile-update form
     * @param string $updateUrl POST target for the main profile-update form
     */
    private function __construct(
        public MenuViewData $menu,
        public ?string $switchedBannerMessage,
        public string $switchIdentityRestoreUrl,
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
            menu: MenuViewData::forAccount($config, $url, $translator),
            switchedBannerMessage: $isSwitched && $originalUser !== null
                ? $translator->translate('voyti.view.admin.switched_banner', ['username' => $originalUser->getUsername()])
                : null,
            switchIdentityRestoreUrl: $url->generate('voyti/admin-users-switch-identity-restore'),
            updateUrl: $url->generate('voyti/profile-update'),
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
