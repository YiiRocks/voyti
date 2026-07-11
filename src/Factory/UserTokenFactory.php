<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Factory;

use YiiRocks\Voyti\Entity\UserToken;
use Yiisoft\Security\Random;

final readonly class UserTokenFactory
{
    public function makeConfirmationToken(int $userId): UserToken
    {
        return $this->make($userId, UserToken::TYPE_CONFIRMATION);
    }

    public function makeConfirmNewMailToken(int $userId): UserToken
    {
        return $this->make($userId, UserToken::TYPE_CONFIRM_NEW_EMAIL);
    }

    public function makeConfirmOldMailToken(int $userId): UserToken
    {
        return $this->make($userId, UserToken::TYPE_CONFIRM_OLD_EMAIL);
    }

    public function makeRecoveryToken(int $userId): UserToken
    {
        return $this->make($userId, UserToken::TYPE_RECOVERY);
    }

    private function generateCode(): string
    {
        return Random::string(32);
    }

    private function make(int $userId, int $type): UserToken
    {
        $userToken = new UserToken();
        $userToken->setUserId($userId);
        $userToken->setType($type);
        $userToken->setCode($this->generateCode());
        $userToken->setCreatedAt(time());
        $userToken->save();
        return $userToken;
    }
}
