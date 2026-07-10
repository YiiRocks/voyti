<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\Entity\UserToken;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Repository\UserTokenRepository;
use YiiRocks\Voyti\Strategy\EmailChangeConfirmation;

final readonly class EmailChangeService
{
    public function __construct(
        private ModuleConfig $config,
        private UserTokenRepository $userTokenRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function run(string $code, User $user): bool|null
    {
        $userToken = $this->userTokenRepository->findByUserIdAndCode(
            $user->getId() !== null ? (int) $user->getId() : 0,
            $code,
        );

        if ($userToken === null || !in_array($userToken->getType(), [UserToken::TYPE_CONFIRM_NEW_EMAIL, UserToken::TYPE_CONFIRM_OLD_EMAIL], true)) {
            return false;
        }

        if ($userToken->getIsExpired($this->config->tokenConfirmationLifespan)) {
            $userToken->delete();
            return false;
        }

        $userToken->delete();

        if ($user->getUnconfirmedEmail() === null) {
            return false;
        }

        $existingUser = $this->userRepository->findByEmail($user->getUnconfirmedEmail());
        if ($existingUser !== null) {
            return false;
        }

        if ($this->config->emailChangeConfirmation === EmailChangeConfirmation::BOTH) {
            if ($userToken->getType() === UserToken::TYPE_CONFIRM_NEW_EMAIL) {
                $user->setFlags($user->getFlags() | User::NEW_EMAIL_CONFIRMED);
                $user->save();
                return true;
            }
            if ($userToken->getType() === UserToken::TYPE_CONFIRM_OLD_EMAIL) {
                $user->setFlags($user->getFlags() | User::OLD_EMAIL_CONFIRMED);
            }
        }

        if (
            ($this->config->emailChangeConfirmation === EmailChangeConfirmation::NEW)
            || ($user->getFlags() & User::NEW_EMAIL_CONFIRMED && $user->getFlags() & User::OLD_EMAIL_CONFIRMED)
        ) {
            $user->setEmail($user->getUnconfirmedEmail());
            $user->setUnconfirmedEmail(null);
            $user->setFlags(0);
            $user->setUpdatedAt(time());
        }

        $user->save();
        return true;
    }
}
