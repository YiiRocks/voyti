<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\ViewData\Registration;

use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Model\UserSocialAccount;
use Yiisoft\Router\UrlGeneratorInterface;

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

    public static function create(UserSocialAccount $account, AuthClientRegistry $authClients, UrlGeneratorInterface $url): self
    {
        return new self(
            providerTitle: $authClients->getTitle($account->getProvider()),
            loginUrl: $url->generate('voyti/session-login'),
            registerUrl: $url->generate('voyti/registration-register'),
        );
    }
}
