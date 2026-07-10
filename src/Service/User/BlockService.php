<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Service\UserSessionHistory\TerminateUserSessionsService;

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
        $result = true;
        $this->eventDispatcher->dispatch(new UserEvent($user));

        if ($user->isBlocked()) {
            $this->terminateUserSessionsService->run($user->getIdOrZero());
        }

        return $result;
    }
}
