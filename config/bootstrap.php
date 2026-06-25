<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

/**
 * @psalm-var callable[]
 */
return [
    static function (ContainerInterface $container): void {
        if (!$container->has(SessionInterface::class) || !$container->has(CurrentUser::class)) {
            return;
        }

        $session = $container->get(SessionInterface::class);

        $sessionName = $session->getName();
        if (isset($_COOKIE[$sessionName]) && $session->getId() === null) {
            $session->setId($_COOKIE[$sessionName]);
        }

        $session->open();

        $authId = $session->get('__auth_id');
        if ($authId === null) {
            return;
        }

        if (!$container->has(IdentityRepositoryInterface::class)) {
            return;
        }

        if ($container->has(ConnectionInterface::class) && !ConnectionProvider::has()) {
            ConnectionProvider::set($container->get(ConnectionInterface::class));
        }

        $currentUser = $container->get(CurrentUser::class);
        $repository = $container->get(IdentityRepositoryInterface::class);
        $identity = $repository->findIdentity((string) $authId);

        if ($identity instanceof IdentityInterface && !$identity instanceof GuestIdentityInterface) {
            $currentUser->overrideIdentity($identity);
        }
    },
];
