<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Registration;

use YiiRocks\Voyti\Model\UserSocialAccount;
use YiiRocks\Voyti\ViewData\Shared\SocialConnectViewData;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\AuthClient\Collection;

/**
 * Data for the `registration/connect` (pending social account) screen.
 */
final readonly class ConnectViewData
{
    private function __construct(
        public string $providerTitle,
        public string $loginUrl,
        public string $registerUrl,
    ) {}

    public static function create(
        UserSocialAccount $account,
        ?Collection $clientCollection,
        UrlGeneratorInterface $url,
    ): self {
        $provider = $account->getProvider();

        return new self(
            providerTitle: SocialConnectViewData::providerTitle($clientCollection, $provider),
            loginUrl: $url->generate('voyti/session-login'),
            registerUrl: $url->generate('voyti/registration-register'),
        );
    }
}
