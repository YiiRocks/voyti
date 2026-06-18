<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Listener;

use YiiRocks\Voyti\Event\EmailChangeEvent;
use YiiRocks\Voyti\Service\MailService;
use YiiRocks\Voyti\Repository\TokenRepository;
use YiiRocks\Voyti\Entity\Token;

final class MailChangeConfirmationListener
{
    public function __construct(
        private readonly MailService $mailService,
        private readonly TokenRepository $tokenRepository,
    ) {
    }

    public function onEmailChange(EmailChangeEvent $event): void
    {
        $user = $event->getUser();
        $tokens = $this->tokenRepository->findByUserId($user->getId() !== null ? (int) $user->getId() : 0);

        foreach ($tokens as $token) {
            if (in_array($token->getType(), [Token::TYPE_CONFIRM_NEW_EMAIL, Token::TYPE_CONFIRM_OLD_EMAIL], true)) {
                $this->mailService->sendReconfirmation($user, $token);
            }
        }
    }
}
