<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\UserEvent;
use YiiRocks\Voyti\Helper\SecurityHelper;

final class UserBlockService
{
    public function __construct(
        private readonly SecurityHelper $securityHelper,
        private readonly EventDispatcherInterface $eventDispatcher,
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

        $result = $user->save();
        $this->eventDispatcher->dispatch(new UserEvent($user));
        return $result;
    }
}
