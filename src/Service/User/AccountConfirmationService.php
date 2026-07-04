<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Repository\UserTokenRepository;

final readonly class AccountConfirmationService
{
    public function __construct(
        private UserTokenRepository $userTokenRepository,
    ) {
    }

    public function run(string $code, User $user, ConfirmationService $confirmationService): bool
    {
        /**
         * @infection-ignore-all
         *
         * ConfirmationService::run() re-checks isConfirmed() and returns false itself
         * before any side effect, so removing this early exit never changes the final
         * return value or any observable state for an already-confirmed user.
         */
        if ($user->isConfirmed()) {
            return false;
        }

        $userToken = $this->userTokenRepository->findByUserIdAndCodeAndType(
            $user->getId() !== null ? (int) $user->getId() : 0,
            $code,
            UserToken::TYPE_CONFIRMATION,
        );

        if ($userToken === null || $userToken->getIsExpired()) {
            return false;
        }

        if (!$confirmationService->run($user)) {
            return false;
        }

        $userToken->delete();
        return true;
    }
}
