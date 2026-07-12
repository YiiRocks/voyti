<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;

final readonly class AccountConfirmationService
{
    public function run(string $code, User $user, ConfirmationService $confirmationService): bool
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

        return $confirmationService->run($user);
    }
}
