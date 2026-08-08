<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\SocialNetwork;

use YiiRocks\Voyti\Helper\LinkButtonHelper;
use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\Widget\AuthChoice;

/**
 * Data for the `social-network/index` screen.
 */
final readonly class IndexViewData
{
    /**
     * @param list<SocialAccountRow> $accounts
     * @param AuthChoice|null $authChoice social login widget, pre-filtered to exclude
     *        already-connected providers - null when `yiisoft/yii-auth-client` isn't installed
     */
    private function __construct(
        public MenuViewData $menu,
        public array $accounts,
        public ?AuthChoice $authChoice,
    ) {}

    /**
     * @param list<UserSocialAccount> $accounts
     * @param list<string> $excludedProviders providers to exclude from the connect-widget's
     *        button list (typically the accounts already connected)
     */
    public static function create(
        array $accounts,
        ?Collection $clientCollection,
        array $excludedProviders,
        VoytiConfig $config,
        UrlGeneratorInterface $url,
        TranslatorInterface $translator,
    ): self {
        $authChoice = null;
        if ($clientCollection !== null) {
            /** @infection-ignore-all The auth-choice widget (route + cosmetic button styling) only renders when the host has configured OAuth clients, so its construction has no behavioural effect the library's own suite can observe. */
            $authChoice = AuthChoice::widget()
                ->authRoute('voyti/session-auth')
                ->linkAttributes(['class' => LinkButtonHelper::submitButtonClass()]);
        }
        $clients = $authChoice?->getClients() ?? [];

        $rows = array_map(
            static function (UserSocialAccount $account) use ($clients, $url): SocialAccountRow {
                $provider = $account->getProvider();
                $title = array_key_exists($provider, $clients) ? $clients[$provider]->getTitle() : $provider;

                return new SocialAccountRow(
                    providerTitle: $title,
                    formSubmitUrl: $url->generate('voyti/user-social-network-delete', ['id' => $account->getId()]),
                );
            },
            $accounts,
        );

        $authChoice?->setClients(array_diff_key($clients, array_flip($excludedProviders)));

        return new self(
            menu: MenuViewData::forAccount($config, $url, $translator),
            accounts: $rows,
            authChoice: $authChoice,
        );
    }
}
