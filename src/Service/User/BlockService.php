<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\UserSession\TerminateUserSessionsService;

/**
 * Toggles a user's blocked status, dispatching the corresponding {@see UserEvent} and terminating
 * all active sessions via {@see TerminateUserSessionsService} when blocking.
 */
final readonly class BlockService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private TerminateUserSessionsService $terminateUserSessionsService,
    ) {}

    public function run(User $user): bool
    {
        $wasBlocked = $user->isBlocked();
        $user->setBlockedAt($wasBlocked ? null : time());
        $user->save();

        $this->eventDispatcher->dispatch(new UserEvent($user, $wasBlocked ? UserEvent::UNBLOCK : UserEvent::BLOCK));

        if (!$wasBlocked) {
            $this->terminateUserSessionsService->run($user->getIdOrZero());
        }

        return true;
    }
}
