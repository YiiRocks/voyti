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

        $userId = $this->getUserId($user);
        $this->userTokenRepository->deleteAllByUserId($userId);

        $this->eventDispatcher->dispatch(new UserEvent($user));
        return true;
    }

    private function getUserId(User $user): int
    {
        /**
         * @infection-ignore-all
         *
         * The getId() === null branch is unreachable: save() is always called
         * before getUserId(), guaranteeing a non-null id.  The fallback 0
         * can never be exercised, so decrementing/incrementing it looks
         * identical.
         */
        return $user->getId() !== null ? (int) $user->getId() : 0;
    }
}
