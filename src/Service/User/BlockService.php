<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\UserSession\TerminateUserSessionsService;

final readonly class BlockService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private TerminateUserSessionsService $terminateUserSessionsService,
    ) {
    }

    public function run(User $user): bool
    {
        if ($user->isBlocked()) {
            $this->eventDispatcher->dispatch(new UserEvent($user));
            $user->setBlockedAt(null);
        } else {
            $this->eventDispatcher->dispatch(new UserEvent($user));
            $user->setBlockedAt(time());
        }

        $user->save();
        $this->eventDispatcher->dispatch(new UserEvent($user));

        if ($user->isBlocked()) {
            $this->terminateUserSessionsService->run($user->getIdOrZero());
        }

        return true;
    }
}
