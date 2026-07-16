<?php

declare(strict_types=1);

use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Event\Auth\AfterRegisterEvent;
use YiiRocks\Voyti\Listener;

return [
    AfterLoginEvent::class => [
        [Listener\PasswordExpirationListener::class, 'onAfterLogin'],
        [Listener\SessionListener::class, 'onAfterLogin'],
    ],
    AfterRegisterEvent::class => [
        [Listener\AdminNotificationListener::class, 'onAfterRegister'],
    ],
];
