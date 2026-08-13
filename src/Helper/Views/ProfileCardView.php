<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper\Views;

use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Helper\UserStatusHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Builds the `shared/view_profile` card data, shared by the profile-owner and admin-viewer screens.
 */
final class ProfileCardView
{
    /**
     * @param bool $showAdminFields when false, email/registeredDisplay/statusLabel/statusBadgeClass
     *        are always null - the card is being shown to the profile owner rather than an admin
     * @param string|null $viewerTimezone the *viewing* admin's own timezone; only used when
     *        $showAdminFields, the registered date is formatted in it rather than the displayed
     *        user's own timezone setting
     * @param string $profilePreviewClass Bootstrap class(es) for the card's wrapping element, so the
     *        embedding screen controls its layout (e.g. `list-group mb-4`)
     *
     * @return array{
     *     displayName: string,
     *     gravatarUrl: string|null,
     *     showAdminFields: bool,
     *     email: string|null,
     *     registeredDisplay: string|null,
     *     statusLabel: string|null,
     *     statusBadgeClass: string|null,
     *     publicEmail: string|null,
     *     location: string|null,
     *     website: string|null,
     *     timezone: string|null,
     *     bio: string|null,
     *     profilePreviewClass: string,
     * }
     */
    public static function create(
        User $user,
        UserProfile $userProfile,
        TranslatorInterface $translator,
        bool $showAdminFields = false,
        ?string $viewerTimezone = null,
        string $profilePreviewClass = 'list-group mb-4',
    ): array {
        $email = null;
        $registeredDisplay = null;
        $statusLabel = null;
        $statusBadgeClass = null;

        if ($showAdminFields) {
            $email = $user->getEmail();
            $registeredDisplay = TimezoneHelper::formatLocalized(
                $user->getCreatedAt(),
                $translator->getLocale(),
                $viewerTimezone,
            );
            [$statusLabel, $statusBadgeClass] = UserStatusHelper::labelAndBadgeClass($user, $translator);
        }

        return [
            'displayName' => $userProfile->getName() ?? $user->getUsername(),
            'gravatarUrl' => $userProfile->getGravatarUrl(),
            'showAdminFields' => $showAdminFields,
            'email' => $email,
            'registeredDisplay' => $registeredDisplay,
            'statusLabel' => $statusLabel,
            'statusBadgeClass' => $statusBadgeClass,
            'publicEmail' => $userProfile->getPublicEmail(),
            'location' => $userProfile->getLocation(),
            'website' => $userProfile->getWebsite(),
            'timezone' => $userProfile->getTimezone(),
            'bio' => $userProfile->getBioParsed(),
            'profilePreviewClass' => $profilePreviewClass,
        ];
    }
}
