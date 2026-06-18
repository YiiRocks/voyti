<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\User;

use YiiRocks\Voyti\Entity\Token;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Helper\SecurityHelper;
use YiiRocks\Voyti\Repository\TokenRepository;
use YiiRocks\Voyti\Service\MailService;

final class ResendConfirmationService
{
    public function __construct(
        private readonly TokenRepository $tokenRepository,
        private readonly MailService $mailService,
        private readonly SecurityHelper $securityHelper,
    ) {
    }

    public function run(User $user): bool
    {
        if ($user->isConfirmed()) {
            return false;
        }

        $userId = $this->getUserId($user);
        $this->tokenRepository->deleteAllByUserId($userId);

        $token = $this->createToken($userId);
        $token->save();

        $this->mailService->sendConfirmation($user, $token);

        return true;
    }

    private function createToken(int $userId): Token
    {
        $token = new Token();
        $token->setUserId($userId);
        $token->setType(Token::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());
        $token->setCode($this->securityHelper->generateRandomString(32));
        return $token;
    }

    private function getUserId(User $user): int
    {
        return $user->getId() !== null ? (int) $user->getId() : 0;
    }
}
