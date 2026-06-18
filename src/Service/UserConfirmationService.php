<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\UserEvent;
use YiiRocks\Voyti\Repository\TokenRepository;

final class UserConfirmationService
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TokenRepository $tokenRepository,
    ) {
    }

    public function run(User $user): bool
    {
        if ($user->isConfirmed()) {
            return false;
        }

        $this->eventDispatcher->dispatch(new UserEvent($user));

        $user->setConfirmedAt(time());
        $result = $user->save();

        $userId = $user->getId() !== null ? (int) $user->getId() : 0;
        $this->tokenRepository->deleteAllByUserId($userId);

        $this->eventDispatcher->dispatch(new UserEvent($user));
        return $result;
    }
}
