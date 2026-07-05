<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Factory\UserTokenFactory;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\MailService;

final readonly class ResendConfirmationService
{
    public function __construct(
        private UserTokenRepository $userTokenRepository,
        private UserTokenFactory $userTokenFactory,
        private MailService $mailService,
    ) {
    }

    public function run(User $user): bool
    {
        if ($user->isConfirmed()) {
            return false;
        }

        $userId = $this->getUserId($user);
        $this->userTokenRepository->deleteAllByUserId($userId);

        $userToken = $this->userTokenFactory->makeConfirmationToken($userId);

        $this->mailService->sendConfirmation($user, $userToken);

        return true;
    }

    private function getUserId(User $user): int
    {
        return $user->getId() !== null ? (int) $user->getId() : 0;
    }
}
