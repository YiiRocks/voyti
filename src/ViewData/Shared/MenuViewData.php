<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Shared;

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
     */
    private function __construct(
        public array $items,
    ) {}

    public static function forAccount(ModuleConfig $config, UrlGeneratorInterface $url, TranslatorInterface $translator): self
    {
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

        return new self($items);
    }

    public static function forAdmin(UrlGeneratorInterface $url, TranslatorInterface $translator): self
    {
        return new self([
            new MenuLinkViewData($translator->translate('voyti.view.dashboard.title'), $url->generate('voyti/admin')),
            new MenuLinkViewData($translator->translate('voyti.view.admin.title'), $url->generate('voyti/admin-users')),
            new MenuLinkViewData($translator->translate('voyti.view.role.title'), $url->generate('voyti/admin-rbac-roles')),
            new MenuLinkViewData($translator->translate('voyti.view.permission.title'), $url->generate('voyti/admin-rbac-permissions')),
            new MenuLinkViewData($translator->translate('voyti.view.rule.title'), $url->generate('voyti/admin-rbac-rules')),
            new MenuLinkViewData($translator->translate('voyti.view.audit_log.title'), $url->generate('voyti/admin-audit-log')),
            new MenuLinkViewData($translator->translate('voyti.menu.logout'), $url->generate('voyti/session-logout'), alignEnd: true),
        ]);
    }
}
