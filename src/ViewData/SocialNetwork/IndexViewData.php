<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\SocialNetwork;

use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use YiiRocks\Voyti\ViewData\Shared\SocialConnectViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Data for the `social-network/index` screen.
 */
final readonly class IndexViewData
{
    /**
     * @param list<SocialAccountRow> $accounts
     */
    private function __construct(
        public MenuViewData $menu,
        public array $accounts,
        public SocialConnectViewData $connect,
    ) {}

    /**
     * @param list<UserSocialAccount> $accounts
     * @param list<string> $excludedProviders
     */
    public static function create(
        array $accounts,
        AuthClientRegistry $authClients,
        array $excludedProviders,
        string $connectRouteName,
        ModuleConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
    ): self {
        $rows = array_map(
            static fn(UserSocialAccount $account): SocialAccountRow => new SocialAccountRow(
                providerTitle: $authClients->getTitle($account->getProvider()),
                formSubmitUrl: $url->generate('voyti/social-network-delete', ['id' => $account->getId() ?? 0]),
            ),
            $accounts,
        );

        return new self(
            menu: MenuViewData::forAccount($config, $url, $translator),
            accounts: $rows,
            connect: SocialConnectViewData::create($authClients, $url, $excludedProviders, $connectRouteName),
        );
    }
}
