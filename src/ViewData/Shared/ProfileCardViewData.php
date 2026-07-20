<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Shared;

use YiiRocks\Voyti\Helper\TimezoneHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserProfile;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `shared/view_profile` card, shared by the profile-owner and admin-viewer screens.
 */
final readonly class ProfileCardViewData
{
    /**
     * @param string|null $gravatarUrl null when the user has no Gravatar-linkable email; templates
     *        should fall back to a placeholder avatar in that case
     * @param bool $showAdminFields when false, $email/$registeredDisplay/$statusLabel/$statusBadgeClass
     *        are always null - the card is being shown to the profile owner rather than an admin
     * @param string|null $registeredDisplay formatted in the *viewing* admin's own timezone (see
     *        $viewerTimezone on {@see self::create()}), not the displayed user's own timezone setting
     * @param string|null $statusLabel a translated status string, paired with $statusBadgeClass (a
     *        Bootstrap `bg-*` class) for a status badge; both null unless $showAdminFields
     * @param string|null $bio plain text (newlines only) - not pre-rendered HTML, encode as usual
     * @param string $profilePreviewClass Bootstrap class(es) for the card's wrapping element, so the
     *        embedding screen controls its layout (e.g. `list-group mb-4`)
     */
    private function __construct(
        public string $displayName,
        public ?string $gravatarUrl,
        public bool $showAdminFields,
        public ?string $email,
        public ?string $registeredDisplay,
        public ?string $statusLabel,
        public ?string $statusBadgeClass,
        public ?string $publicEmail,
        public ?string $location,
        public ?string $website,
        public ?string $timezone,
        public ?string $bio,
        public string $profilePreviewClass,
    ) {}

    public static function create(
        User $user,
        UserProfile $userProfile,
        TranslatorInterface $translator,
        bool $showAdminFields = false,
        ?string $viewerTimezone = null,
        string $profilePreviewClass = 'list-group mb-4',
    ): self {
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
            [$statusLabel, $statusBadgeClass] = match (true) {
                $user->isBlocked() => [$translator->translate('voyti.view.status_blocked'), 'bg-danger'],
                $user->isConfirmed() => [$translator->translate('voyti.view.status_active'), 'bg-success'],
                default => [$translator->translate('voyti.view.status_pending'), 'bg-warning text-dark'],
            };
        }

        return new self(
            displayName: $userProfile->getName() ?? $user->getUsername(),
            gravatarUrl: $userProfile->getGravatarUrl(),
            showAdminFields: $showAdminFields,
            email: $email,
            registeredDisplay: $registeredDisplay,
            statusLabel: $statusLabel,
            statusBadgeClass: $statusBadgeClass,
            publicEmail: $userProfile->getPublicEmail(),
            location: $userProfile->getLocation(),
            website: $userProfile->getWebsite(),
            timezone: $userProfile->getTimezone(),
            bio: $userProfile->getBioParsed(),
            profilePreviewClass: $profilePreviewClass,
        );
    }
}
