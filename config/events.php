<?php

declare(strict_types=1);

use YiiRocks\Voyti\Event;
use YiiRocks\Voyti\Listener;

return [
    Event\AfterLoginEvent::class => [
        [Listener\PasswordExpirationListener::class, 'onAfterLogin'],
        [Listener\SessionHistoryListener::class, 'onAfterLogin'],
    ],
    Event\AfterRegisterEvent::class => [
        [Listener\AdminNotificationListener::class, 'onAfterRegister'],
    ],
    Event\EmailChangeEvent::class => [
        [Listener\MailChangeConfirmationListener::class, 'onEmailChange'],
    ],
];
