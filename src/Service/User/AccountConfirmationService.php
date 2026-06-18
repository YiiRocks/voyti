<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use YiiRocks\Voyti\Entity\Token;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Repository\TokenRepository;

final class AccountConfirmationService
{
    public function __construct(
        private readonly TokenRepository $tokenRepository,
    ) {
    }

    public function run(string $code, User $user, ConfirmationService $confirmationService): bool
    {
        if ($user->isConfirmed()) {
            return false;
        }

        $token = $this->tokenRepository->findByUserIdAndCodeAndType(
            $user->getId() !== null ? (int) $user->getId() : 0,
            $code,
            Token::TYPE_CONFIRMATION,
        );

        if ($token === null || $token->getIsExpired()) {
            return false;
        }

        if (!$confirmationService->run($user)) {
            return false;
        }

        $token->delete();
        return true;
    }
}
