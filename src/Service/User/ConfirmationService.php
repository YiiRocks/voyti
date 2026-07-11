<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;

final readonly class ConfirmationService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function run(User $user): bool
    {
        if ($user->isConfirmed()) {
            return false;
        }

        $this->eventDispatcher->dispatch(new UserEvent($user));

        $user->setConfirmedAt(time());
        $user->save();

        UserToken::deleteAllByUserId($user->getIdOrZero());

        $this->eventDispatcher->dispatch(new UserEvent($user));
        return true;
    }
}
