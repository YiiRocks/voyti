<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Listener;

use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\MailService;

final class AdminNotificationListener
{
    public function __construct(
        private readonly MailService $mailService,
        private readonly ModuleConfig $config,
    ) {
    }

    public function onAfterRegister(AfterRegisterEvent $event): void
    {
        if ($this->config->mailAdminOnRegister === null) {
            return;
        }
        $this->mailService->sendAdminNotification(
            $this->config->mailAdminOnRegister,
            $event->getUser(),
        );
    }
}
