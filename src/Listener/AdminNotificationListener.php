<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Listener;

use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\VoytiConfig;

/**
 * Listens for {@see AfterRegisterEvent} and emails the configured admin address
 * (`VoytiConfig::$mailAdminOnRegister`) about the new registration, when set.
 */
final readonly class AdminNotificationListener
{
    public function __construct(
        private MailService $mailService,
        private VoytiConfig $config,
    ) {}

    public function onAfterRegister(AfterRegisterEvent $event): void
    {
        if ($this->config->mailAdminOnRegister === null || $this->config->mailAdminOnRegister === '') {
            return;
        }
        $this->mailService->sendAdminNotification(
            $this->config->mailAdminOnRegister,
            $event->getUser(),
        );
    }
}
