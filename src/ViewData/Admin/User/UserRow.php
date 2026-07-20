<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Admin\User;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * A single row on the `admin/user/index` screen.
 */
final readonly class UserRow
{
    /**
     * @param string $statusLabel translated status text, paired with $statusBadgeClass (a
     *        Bootstrap `bg-*` class) for a status badge
     * @param bool $showConfirmAction whether to render the "confirm" action ($confirmUrl) - false
     *        once the user is already confirmed
     * @param bool $showForcePasswordChangeAction whether the force-password-change feature is
     *        enabled at all (`ModuleConfig::$enablePasswordExpiration`); unrelated to this user's
     *        own state
     * @param bool $showSwitchIdentityAction whether switch-identity is enabled and the viewing
     *        admin isn't already impersonating someone
     * @param bool $switchIdentityDisabled true when $showSwitchIdentityAction is true but this
     *        particular row's switch-identity button should still render disabled (e.g. this row
     *        is the viewing admin themselves)
     * @param string $showUrl a link (GET) to this user's detail screen, not a form target
     * @param string $updateUrl a link (GET) to the update-account screen, not a form target
     * @param string $updateProfileUrl a link (GET) to the update-profile screen, not a form target
     * @param string $sessionsUrl a link (GET) to this user's sessions screen, not a form target
     * @param string $confirmUrl POST target for the "confirm user" form
     * @param string $forcePasswordChangeUrl POST target for the "force password change" form
     * @param string $passwordResetUrl POST target for the "send password reset email" form
     * @param string $switchIdentityUrl POST target for the "switch identity to this user" form
     * @param string $blockToggleUrl POST target for the block/unblock form
     * @param string $blockToggleLabel translated button text - already says "Block" or "Unblock"
     *        depending on the user's current blocked state
     * @param string $deleteUrl POST target for the delete-user form
     */
    private function __construct(
        public int $id,
        public string $username,
        public string $email,
        public string $statusLabel,
        public string $statusBadgeClass,
        public bool $showConfirmAction,
        public bool $showForcePasswordChangeAction,
        public bool $showSwitchIdentityAction,
        public bool $switchIdentityDisabled,
        public string $showUrl,
        public string $updateUrl,
        public string $updateProfileUrl,
        public string $sessionsUrl,
        public string $confirmUrl,
        public string $forcePasswordChangeUrl,
        public string $passwordResetUrl,
        public string $switchIdentityUrl,
        public string $blockToggleUrl,
        public string $blockToggleLabel,
        public string $deleteUrl,
    ) {}

    public static function create(
        User $user,
        ModuleConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
        bool $isSwitched,
        int $currentUserId,
    ): self {
        $id = $user->getIdOrZero();
        [$statusLabel, $statusBadgeClass] = match (true) {
            $user->isBlocked() => [$translator->translate('voyti.view.status_blocked'), 'bg-danger'],
            $user->isConfirmed() => [$translator->translate('voyti.view.status_active'), 'bg-success'],
            default => [$translator->translate('voyti.view.status_pending'), 'bg-warning text-dark'],
        };

        return new self(
            id: $id,
            username: $user->getUsername(),
            email: $user->getEmail(),
            statusLabel: $statusLabel,
            statusBadgeClass: $statusBadgeClass,
            showConfirmAction: !$user->isConfirmed(),
            showForcePasswordChangeAction: $config->enablePasswordExpiration,
            showSwitchIdentityAction: $config->enableSwitchIdentities && !$isSwitched,
            switchIdentityDisabled: $user->isSwitchDisabledFor($currentUserId),
            showUrl: $url->generate('voyti/admin-users-show', ['id' => $id]),
            updateUrl: $url->generate('voyti/admin-users-update', ['id' => $id]),
            updateProfileUrl: $url->generate('voyti/admin-users-update-profile', ['id' => $id]),
            sessionsUrl: $url->generate('voyti/admin-users-sessions', ['id' => $id]),
            confirmUrl: $url->generate('voyti/admin-users-confirm', ['id' => $id]),
            forcePasswordChangeUrl: $url->generate('voyti/admin-users-force-password-change', ['id' => $id]),
            passwordResetUrl: $url->generate('voyti/admin-users-password-reset', ['id' => $id]),
            switchIdentityUrl: $url->generate('voyti/admin-users-switch-identity', ['id' => $id]),
            blockToggleUrl: $url->generate('voyti/admin-users-block', ['id' => $id]),
            blockToggleLabel: $translator->translate($user->isBlocked() ? 'voyti.view.unblock_button' : 'voyti.view.block_button'),
            deleteUrl: $url->generate('voyti/admin-users-delete', ['id' => $id]),
        );
    }
}
