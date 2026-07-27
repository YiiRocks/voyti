<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Shared;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * A navigation menu (account settings menu or admin menu), pre-resolved from {@see ModuleConfig}
 * feature flags and route names so templates never need either.
 */
final readonly class MenuViewData
{
    /**
     * @param list<MenuLinkViewData> $items
     * @param string|null $switchedBannerMessage set only when an admin is currently impersonating
     *        another user (see {@see \YiiRocks\Voyti\Service\SwitchIdentityService}); pair with
     *        $switchIdentityRestoreUrl/$switchIdentityRestoreButtonLabel to offer a "restore my
     *        identity" action on every account settings page
     * @param string|null $switchIdentityRestoreUrl POST target restoring the admin's original
     *        identity, set together with $switchedBannerMessage
     * @param string|null $switchIdentityRestoreButtonLabel already-translated, set together with
     *        $switchedBannerMessage
     */
    private function __construct(
        public array $items,
        public ?string $switchedBannerMessage,
        public ?string $switchIdentityRestoreUrl,
        public ?string $switchIdentityRestoreButtonLabel,
    ) {}

    public static function forAccount(
        ModuleConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
        bool $isSwitched,
        ?User $originalUser,
    ): self {
        $items = [
            new MenuLinkViewData($translator->translate('voyti.menu.dashboard'), $url->generate('voyti/user')),
            new MenuLinkViewData($translator->translate('voyti.menu.userProfile'), $url->generate('voyti/user-profile')),
            new MenuLinkViewData($translator->translate('voyti.menu.account'), $url->generate('voyti/user-account')),
            new MenuLinkViewData($translator->translate('voyti.menu.networks'), $url->generate('voyti/user-social-network')),
            new MenuLinkViewData($translator->translate('voyti.menu.sessions'), $url->generate('voyti/user-account-sessions')),
        ];

        if ($config->enableTwoFactorAuthentication) {
            $items[] = new MenuLinkViewData($translator->translate('voyti.menu.two_factor'), $url->generate('voyti/user-two-factor'));
        }

        if ($config->enableGdprCompliance || $config->allowAccountDelete) {
            $items[] = new MenuLinkViewData($translator->translate('voyti.view.settings.privacy'), $url->generate('voyti/user-privacy'));
        }

        $items[] = new MenuLinkViewData($translator->translate('voyti.menu.logout'), $url->generate('voyti/session-logout'), alignEnd: true);

        $isSwitched = $isSwitched && $originalUser !== null;

        return new self(
            items: $items,
            switchedBannerMessage: $isSwitched
                ? $translator->translate('voyti.view.admin.switched_banner', ['username' => $originalUser->getUsername()])
                : null,
            switchIdentityRestoreUrl: $isSwitched ? $url->generate('voyti/admin-users-switch-identity-restore') : null,
            switchIdentityRestoreButtonLabel: $isSwitched ? $translator->translate('voyti.view.admin.restore_button') : null,
        );
    }

    public static function forAdmin(UrlGeneratorInterface $url, TranslatorInterface $translator): self
    {
        return new self(
            items: [
                new MenuLinkViewData($translator->translate('voyti.view.dashboard.title'), $url->generate('voyti/admin')),
                new MenuLinkViewData($translator->translate('voyti.view.admin.title'), $url->generate('voyti/admin-users')),
                new MenuLinkViewData($translator->translate('voyti.view.role.title'), $url->generate('voyti/admin-rbac-roles')),
                new MenuLinkViewData($translator->translate('voyti.view.permission.title'), $url->generate('voyti/admin-rbac-permissions')),
                new MenuLinkViewData($translator->translate('voyti.view.rule.title'), $url->generate('voyti/admin-rbac-rules')),
                new MenuLinkViewData($translator->translate('voyti.view.audit_log.title'), $url->generate('voyti/admin-audit-log')),
                new MenuLinkViewData($translator->translate('voyti.menu.logout'), $url->generate('voyti/session-logout'), alignEnd: true),
            ],
            switchedBannerMessage: null,
            switchIdentityRestoreUrl: null,
            switchIdentityRestoreButtonLabel: null,
        );
    }
}
