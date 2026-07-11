<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\UserSessionHistory;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Session\SessionEvent;
use YiiRocks\Voyti\Model\UserSessionHistory;

final readonly class TerminateUserSessionsService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function run(int $userId): void
    {
        (new UserSessionHistory())->deleteAll(['user_id' => $userId]);

        $this->eventDispatcher->dispatch(new SessionEvent($userId, '', ['type' => SessionEvent::SESSION_TERMINATED]));
    }
}
