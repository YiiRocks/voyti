<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Helper\Views;

use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Builds the pre-resolved navigation menus (account settings menu or admin menu) from
 * {@see VoytiConfig} feature flags and route names, so templates never need either.
 */
final class MenuView
{
    /**
     * @return list<array{label: string, url: string, alignEnd: bool, routeName: string|null}>
     */
    public static function account(
        VoytiConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
    ): array {
        $items = [
            self::link($translator->translate('voyti.menu.dashboard'), $url->generate('voyti/user')),
            self::link($translator->translate('voyti.menu.userProfile'), $url->generate('voyti/user-profile')),
            self::link($translator->translate('voyti.menu.account'), $url->generate('voyti/user-account')),
            self::link($translator->translate('voyti.menu.networks'), $url->generate('voyti/user-social-network')),
            self::link($translator->translate('voyti.menu.sessions'), $url->generate('voyti/user-account-sessions')),
        ];

        // Packages (e.g. yiirocks/voyti-2fa) contribute account-menu links via the accountMenuItems
        // config, so core needs no knowledge of them.
        foreach ($config->accountMenuItems as $item) {
            $items[] = self::link(
                $translator->translate($item['label'], category: $item['category']),
                $url->generate($item['route']),
            );
        }

        if ($config->enableGdprCompliance || $config->allowAccountDelete) {
            $items[] = self::link($translator->translate('voyti.view.settings.privacy'), $url->generate('voyti/user-privacy'));
        }

        $items[] = self::link(
            $translator->translate('voyti.menu.logout'),
            $url->generate('voyti/session-logout'),
            alignEnd: true,
            routeName: 'voyti/session-logout',
        );

        return $items;
    }

    /**
     * @return list<array{label: string, url: string, alignEnd: bool, routeName: string|null}>
     */
    public static function admin(UrlGeneratorInterface $url, TranslatorInterface $translator): array
    {
        return [
            self::link($translator->translate('voyti.view.dashboard.title'), $url->generate('voyti/admin')),
            self::link($translator->translate('voyti.view.admin.title'), $url->generate('voyti/admin-users')),
            self::link($translator->translate('voyti.view.role.title'), $url->generate('voyti/admin-rbac-roles')),
            self::link($translator->translate('voyti.view.permission.title'), $url->generate('voyti/admin-rbac-permissions')),
            self::link($translator->translate('voyti.view.rule.title'), $url->generate('voyti/admin-rbac-rules')),
            self::link($translator->translate('voyti.view.audit_log.title'), $url->generate('voyti/admin-audit-log')),
            self::link(
                $translator->translate('voyti.menu.logout'),
                $url->generate('voyti/session-logout'),
                alignEnd: true,
                routeName: 'voyti/session-logout',
            ),
        ];
    }

    /**
     * @return array{label: string, url: string, alignEnd: bool, routeName: string|null}
     */
    private static function link(
        string $label,
        string $url,
        bool $alignEnd = false,
        ?string $routeName = null,
    ): array {
        return [
            'label' => $label,
            'url' => $url,
            'alignEnd' => $alignEnd,
            'routeName' => $routeName,
        ];
    }
}
