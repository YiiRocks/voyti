<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use Yiisoft\Security\Random;
use YiiRocks\Voyti\Service\MailService;

final class ResendConfirmationService
{
    public function __construct(
        private readonly UserTokenRepository $userTokenRepository,
        private readonly MailService $mailService,
    ) {
    }

    public function run(User $user): bool
    {
        if ($user->isConfirmed()) {
            return false;
        }

        $userId = $this->getUserId($user);
        $this->userTokenRepository->deleteAllByUserId($userId);

        $userToken = $this->createToken($userId);
        $userToken->save();

        $this->mailService->sendConfirmation($user, $userToken);

        return true;
    }

    private function createToken(int $userId): UserToken
    {
        $userToken = new UserToken();
        $userToken->setUserId($userId);
        $userToken->setType(UserToken::TYPE_CONFIRMATION);
        $userToken->setCreatedAt(time());
        $userToken->setCode(Random::string(32));
        return $userToken;
    }

    private function getUserId(User $user): int
    {
        return $user->getId() !== null ? (int) $user->getId() : 0;
    }
}
