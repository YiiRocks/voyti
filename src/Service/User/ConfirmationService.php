<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\User\UserEvent;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use YiiRocks\Voyti\Service\MailService;

final readonly class ConfirmationService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private UserTokenFactory $userTokenFactory,
        private MailService $mailService,
    ) {
    }

    public function confirmWithCode(string $code, User $user): bool
    {
        if ($user->isConfirmed()) {
            return false;
        }

        $userToken = UserToken::findByUserIdAndCodeAndType(
            $user->getIdOrZero(),
            $code,
            UserToken::TYPE_CONFIRMATION,
        );

        if ($userToken === null || $userToken->getIsExpired()) {
            return false;
        }

        return $this->run($user);
    }

    public function resend(User $user): bool
    {
        if ($user->isConfirmed()) {
            return false;
        }

        $userId = $user->getIdOrZero();
        UserToken::deleteAllByUserId($userId);

        $userToken = $this->userTokenFactory->makeConfirmationToken($userId);

        $this->mailService->sendConfirmation($user, $userToken);

        return true;
    }

    public function run(User $user): bool
    {
        if ($user->isConfirmed()) {
            return false;
        }

        $this->eventDispatcher->dispatch(new UserEvent($user));

        $user->setConfirmedAt(time());
        $user->save();

        UserToken::deleteAllByUserIdAndType($user->getIdOrZero(), UserToken::TYPE_CONFIRMATION);

        $this->eventDispatcher->dispatch(new UserEvent($user));
        return true;
    }
}
