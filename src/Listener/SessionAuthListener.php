<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Listener;

use Psr\Container\ContainerInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\User\Event\AfterLogin;
use Yiisoft\User\Event\AfterLogout;

final class SessionAuthListener
{
    private const SESSION_AUTH_ID = '__auth_id';

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function onLogin(AfterLogin $event): void
    {
        $session = $this->getSession();
        if ($session === null) {
            return;
        }

        $id = $event->getIdentity()->getId();
        if ($id !== null) {
            $session->set(self::SESSION_AUTH_ID, $id);
        }
    }

    public function onLogout(AfterLogout $event): void
    {
        $session = $this->getSession();
        if ($session === null) {
            return;
        }

        $session->remove(self::SESSION_AUTH_ID);
    }

    private function getSession(): ?SessionInterface
    {
        if (!$this->container->has(SessionInterface::class)) {
            return null;
        }

        $session = $this->container->get(SessionInterface::class);
        return $session instanceof SessionInterface ? $session : null;
    }
}
