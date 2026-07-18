<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\UserSession;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\UserSessions;

/**
 * Deletes all {@see UserSessions} rows for a user and dispatches a
 * {@see SessionEvent::SESSION_TERMINATED} event.
 */
final readonly class TerminateUserSessionsService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function run(int $userId): void
    {
        (new UserSessions())->deleteAll(['user_id' => $userId]);

        $this->eventDispatcher->dispatch(new SessionEvent($userId, '', ['type' => SessionEvent::SESSION_TERMINATED]));
    }
}
