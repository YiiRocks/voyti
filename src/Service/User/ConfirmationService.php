<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Repository\UserTokenRepository;

final readonly class ConfirmationService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private UserTokenRepository $userTokenRepository,
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

        $this->userTokenRepository->deleteAllByUserId($user->getIdOrZero());

        $this->eventDispatcher->dispatch(new UserEvent($user));
        return true;
    }
}
