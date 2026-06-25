<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Listener;

use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\ExpireService;

final class PasswordExpirationListener
{
    public function __construct(
        private readonly ExpireService $passwordExpireService,
        private readonly ModuleConfig $config,
    ) {
    }

    public function onAfterLogin(AfterLoginEvent $event): void
    {
        if (!$this->config->enablePasswordExpiration) {
            return;
        }
        $user = $event->getUser();
        $this->passwordExpireService->checkPasswordExpiration($user);
    }
}
