<?php

declare(strict_types=1);

use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\Event\Security\EmailChangeEvent;
use YiiRocks\Voyti\Listener;

return [
    AfterLoginEvent::class => [
        [Listener\PasswordExpirationListener::class, 'onAfterLogin'],
        [Listener\SessionHistoryListener::class, 'onAfterLogin'],
    ],
    AfterRegisterEvent::class => [
        [Listener\AdminNotificationListener::class, 'onAfterRegister'],
    ],
    EmailChangeEvent::class => [
        [Listener\MailChangeConfirmationListener::class, 'onEmailChange'],
    ],
];
