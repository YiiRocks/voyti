<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service;

use YiiRocks\Voyti\Entity\Token;
use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use YiiRocks\Voyti\Repository\TokenRepository;
use YiiRocks\Voyti\Repository\UserRepository;
use YiiRocks\Voyti\Strategy\MailChangeStrategyInterface;

final class EmailChangeService
{
    public function __construct(
        private readonly ModuleConfig $config,
        private readonly TokenRepository $tokenRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function run(string $code, User $user): bool|null
    {
        $token = $this->tokenRepository->findByUserIdAndCode(
            $user->getId() !== null ? (int) $user->getId() : 0,
            $code,
        );

        if ($token === null || !in_array($token->getType(), [Token::TYPE_CONFIRM_NEW_EMAIL, Token::TYPE_CONFIRM_OLD_EMAIL], true)) {
            return false;
        }

        if ($token->getIsExpired()) {
            $token->delete();
            return false;
        }

        $token->delete();

        if ($user->getUnconfirmedEmail() === null) {
            return false;
        }

        $existingUser = $this->userRepository->findByEmail($user->getUnconfirmedEmail());
        if ($existingUser !== null) {
            return false;
        }

        if ($this->config->emailChangeStrategy === MailChangeStrategyInterface::TYPE_SECURE) {
            if ($token->getType() === Token::TYPE_CONFIRM_NEW_EMAIL) {
                $user->setFlags($user->getFlags() | User::NEW_EMAIL_CONFIRMED);
                $user->save();
                return true;
            }
            if ($token->getType() === Token::TYPE_CONFIRM_OLD_EMAIL) {
                $user->setFlags($user->getFlags() | User::OLD_EMAIL_CONFIRMED);
            }
        }

        if (
            ($this->config->emailChangeStrategy === MailChangeStrategyInterface::TYPE_DEFAULT)
            || ($user->getFlags() & User::NEW_EMAIL_CONFIRMED && $user->getFlags() & User::OLD_EMAIL_CONFIRMED)
        ) {
            $user->setEmail($user->getUnconfirmedEmail() ?? $user->getEmail());
            $user->setUnconfirmedEmail(null);
            $user->setFlags(0);
            $user->setUpdatedAt(time());
            return $user->save();
        }

        return $user->save();
    }
}
