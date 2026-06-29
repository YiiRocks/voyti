<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;

/**
 * @psalm-var callable[]
 */
return [
    static function (ContainerInterface $container): void {
        if (!$container->has(SessionInterface::class)) {
            return;
        }

        $session = $container->get(SessionInterface::class);

        $sessionName = $session->getName();
        if (isset($_COOKIE[$sessionName]) && $session->getId() === null) {
            $session->setId($_COOKIE[$sessionName]);
        }

        $session->open();

        if (!$container->has(CurrentUser::class)) {
            return;
        }

        $currentUser = $container->get(CurrentUser::class);
        if ($currentUser instanceof CurrentUser) {
            (function (SessionInterface $session): void {
                $this->session = $session;
            })->call($currentUser, $session);

            if (!ConnectionProvider::has() && $container->has(ConnectionInterface::class)) {
                ConnectionProvider::set($container->get(ConnectionInterface::class));
            }

            if ($currentUser->isGuest()
                && $container->has(RememberMeCookieService::class)
                && $container->has(IdentityRepositoryInterface::class)
                && ConnectionProvider::has()
            ) {
                $rememberMeCookieService = $container->get(RememberMeCookieService::class);
                $identityRepository = $container->get(IdentityRepositoryInterface::class);

                if (
                    $rememberMeCookieService instanceof RememberMeCookieService
                    && $identityRepository instanceof IdentityRepositoryInterface
                ) {
                    $rememberMeCookieService->loginByCookie($_COOKIE, $currentUser, $identityRepository);
                }
            }
        }
    },
];
