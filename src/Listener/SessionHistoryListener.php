<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Listener;

use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\UserSessionHistory\UserSessionHistoryDecorator;

final class SessionHistoryListener
{
    public function __construct(
        private readonly UserSessionHistoryDecorator $sessionHistoryDecorator,
        private readonly ModuleConfig $config,
    ) {
    }

    public function onAfterLogin(AfterLoginEvent $event): void
    {
        if (!$this->config->enableSessionHistory) {
            return;
        }
        $user = $event->getUser();
        $this->sessionHistoryDecorator->registerLogin($user);
    }
}
