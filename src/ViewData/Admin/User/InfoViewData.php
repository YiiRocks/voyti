<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\User;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use YiiRocks\Voyti\ViewData\Shared\ProfileCardViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `admin/user/_info` screen.
 */
final readonly class InfoViewData
{
    private function __construct(
        public MenuViewData $menu,
        public string $username,
        public ProfileCardViewData $profile,
    ) {}

    public static function create(
        User $user,
        UserProfile $userProfile,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
        ?string $viewerTimezone,
    ): self {
        return new self(
            menu: MenuViewData::forAdmin($url, $translator),
            username: $user->getUsername(),
            profile: ProfileCardViewData::create(
                $user,
                $userProfile,
                $translator,
                showAdminFields: true,
                viewerTimezone: $viewerTimezone,
                profilePreviewClass: 'list-group list-group-flush',
            ),
        );
    }
}
