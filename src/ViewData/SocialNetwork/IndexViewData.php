<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\SocialNetwork;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use YiiRocks\Voyti\ViewData\Shared\SocialConnectViewData;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\AuthClient\Collection;

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
        ?Collection $clientCollection,
        array $excludedProviders,
        string $connectRouteName,
        VoytiConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
        bool $isSwitched,
        ?User $originalUser,
    ): self {
        $rows = array_map(
            static function (UserSocialAccount $account) use ($clientCollection, $url): SocialAccountRow {
                $provider = $account->getProvider();

                return new SocialAccountRow(
                    providerTitle: SocialConnectViewData::providerTitle($clientCollection, $provider),
                    formSubmitUrl: $url->generate('voyti/user-social-network-delete', ['id' => $account->getId()]),
                );
            },
            $accounts,
        );

        return new self(
            menu: MenuViewData::forAccount($config, $url, $translator, $isSwitched, $originalUser),
            accounts: $rows,
            connect: SocialConnectViewData::create($clientCollection, $url, $excludedProviders, $connectRouteName),
        );
    }
}
