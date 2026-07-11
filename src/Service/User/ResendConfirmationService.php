<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Service\MailService;

final readonly class ResendConfirmationService
{
    public function __construct(
        private UserTokenFactory $userTokenFactory,
        private MailService $mailService,
    ) {
    }

    public function run(User $user): bool
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
}
