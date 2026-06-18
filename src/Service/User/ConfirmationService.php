<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Repository\TokenRepository;

final class ConfirmationService
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TokenRepository $tokenRepository,
    ) {
    }

    /**
     * @return false|null
     */
    public function run(User $user): bool|null
    {
        if ($user->isConfirmed()) {
            return false;
        }

        $this->eventDispatcher->dispatch(new UserEvent($user));

        $user->setConfirmedAt(time());
        $result = $user->save();

        $userId = $this->getUserId($user);
        $this->tokenRepository->deleteAllByUserId($userId);

        $this->eventDispatcher->dispatch(new UserEvent($user));
        return $result;
    }

    private function getUserId(User $user): int
    {
        return $user->getId() !== null ? (int) $user->getId() : 0;
    }
}
