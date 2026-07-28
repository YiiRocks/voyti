<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\Auth;

use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\AuthClient\Collection;

/**
 * `OAuth2::getOauth2ReturnUrl()` has no request-derived fallback - a client whose return URL was
 * never set sends providers an empty `redirect_uri`, which strict providers like Google reject
 * outright. There is only ever one correct value for it though: the `voyti/session-auth` URL for
 * that client's own key in the `Collection`, so hosts don't need to configure it by hand - this
 * fills it in for every client that doesn't already have one, keyed the same way `AuthAction`
 * itself looks clients up (by `Collection` key, not `AuthClientInterface::getName()`, since those
 * can differ - see {@see SocialProviderTitleResolver}).
 */
final readonly class SocialAuthClientReturnUrlConfigurator
{
    public function __construct(
        private UrlGeneratorInterface $url,
    ) {}

    public function configure(Collection $clientCollection): Collection
    {
        foreach ($clientCollection->getClients() as $authClientKey => $client) {
            if ($client->getOauth2ReturnUrl() === '') {
                $client->setOauth2ReturnUrl(
                    $this->url->generateAbsolute('voyti/session-auth', ['authclient' => $authClientKey]),
                );
            }
        }

        return $clientCollection;
    }
}
