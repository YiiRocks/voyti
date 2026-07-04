<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Listener;

use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\Password\ExpireService;

final readonly class PasswordExpirationListener
{
    public function __construct(
        private ExpireService $passwordExpireService,
        private ModuleConfig $config,
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
