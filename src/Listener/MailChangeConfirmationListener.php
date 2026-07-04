<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Listener;

use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\Event\Security\EmailChangeEvent;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Service\MailService;

final readonly class MailChangeConfirmationListener
{
    public function __construct(
        private MailService $mailService,
        private UserTokenRepository $userTokenRepository,
    ) {
    }

    public function onEmailChange(EmailChangeEvent $event): void
    {
        $user = $event->getUser();
        $tokens = $this->userTokenRepository->findByUserId($user->getId() !== null ? (int) $user->getId() : 0);

        foreach ($tokens as $userToken) {
            if (in_array($userToken->getType(), [UserToken::TYPE_CONFIRM_NEW_EMAIL, UserToken::TYPE_CONFIRM_OLD_EMAIL], true)) {
                $this->mailService->sendReconfirmation($user, $userToken);
            }
        }
    }
}
