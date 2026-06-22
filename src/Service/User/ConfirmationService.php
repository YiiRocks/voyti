<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Repository\UserTokenRepository;

final class ConfirmationService
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UserTokenRepository $userTokenRepository,
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

        $userId = $this->getUserId($user);
        $this->userTokenRepository->deleteAllByUserId($userId);

        $this->eventDispatcher->dispatch(new UserEvent($user));
        return true;
    }

    private function getUserId(User $user): int
    {
        return $user->getId() !== null ? (int) $user->getId() : 0;
    }
}
