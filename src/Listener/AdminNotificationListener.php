<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Listener;

use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Service\MailService;

final readonly class AdminNotificationListener
{
    public function __construct(
        private MailService $mailService,
        private ModuleConfig $config,
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
