<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Listener;

use YiiRocks\Voyti\Event\AfterLoginEvent;
use YiiRocks\Voyti\Service\PasswordExpireService;
use YiiRocks\Voyti\ModuleConfig;

final class PasswordExpirationListener
{
    public function __construct(
        private readonly PasswordExpireService $passwordExpireService,
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
