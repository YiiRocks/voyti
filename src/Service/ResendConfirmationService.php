<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use YiiRocks\Voyti\Entity\Token;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Helper\SecurityHelper;
use YiiRocks\Voyti\Repository\TokenRepository;

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

        $this->tokenRepository->deleteAllByUserId($user->getId() !== null ? (int) $user->getId() : 0);

        $token = new Token();
        $token->setUserId($user->getId() !== null ? (int) $user->getId() : 0);
        $token->setType(Token::TYPE_CONFIRMATION);
        $token->setCreatedAt(time());
        $token->setCode($this->securityHelper->generateRandomString(32));
        $token->save();

        $this->mailService->sendConfirmation($user, $token);

        return true;
    }
}
