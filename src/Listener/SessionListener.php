<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Listener;

use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Service\UserSession\UserSessionDecorator;

/**
 * Listens for {@see AfterLoginEvent} and registers the newly logged-in session via
 * `UserSessionDecorator`, so session tracking stays out of the login controller/service flow.
 */
final readonly class SessionListener
{
    public function __construct(
        private UserSessionDecorator $userSessionDecorator,
    ) {}

    public function onAfterLogin(AfterLoginEvent $event): void
    {
        $user = $event->getUser();
        $this->userSessionDecorator->registerLogin($user, $event->getPreviousSessionId());
    }
}
