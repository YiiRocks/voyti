<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\UserSession;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\UserSessions;

/**
 * Marks all {@see UserSessions} rows for a user as revoked and dispatches a
 * {@see SessionEvent::SESSION_TERMINATED} event.
 */
final readonly class TerminateUserSessionsService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function run(int $userId): void
    {
        (new UserSessions())->updateAll(
            ['revoked_at' => time()],
            ['and', ['user_id' => $userId], ['revoked_at' => null]],
        );

        $this->eventDispatcher->dispatch(new SessionEvent($userId, '', ['type' => SessionEvent::SESSION_TERMINATED]));
    }
}
