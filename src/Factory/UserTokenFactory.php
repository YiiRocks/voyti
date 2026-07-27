<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Factory;

use YiiRocks\Voyti\Model\UserToken;
use Yiisoft\Security\Random;

/**
 * Creates and persists `UserToken` rows (confirmation, email-change, and password-recovery
 * tokens) with a random code, returning the raw code for the caller to embed in a link — only its
 * SHA-256 hash is persisted, following the same pattern as {@see \YiiRocks\Voyti\Service\User\ApiTokenService}.
 */
final readonly class UserTokenFactory
{
    public function makeConfirmationToken(int $userId): string
    {
        return $this->make($userId, UserToken::TYPE_CONFIRMATION);
    }

    public function makeConfirmNewMailToken(int $userId): string
    {
        return $this->make($userId, UserToken::TYPE_CONFIRM_NEW_EMAIL);
    }

    public function makeConfirmOldMailToken(int $userId): string
    {
        return $this->make($userId, UserToken::TYPE_CONFIRM_OLD_EMAIL);
    }

    public function makeRecoveryToken(int $userId): string
    {
        return $this->make($userId, UserToken::TYPE_RECOVERY);
    }

    private function generateCode(): string
    {
        return Random::string(32);
    }

    private function make(int $userId, int $type): string
    {
        $rawCode = $this->generateCode();

        $userToken = new UserToken();
        $userToken->setUserId($userId);
        $userToken->setType($type);
        $userToken->setCode(hash('sha256', $rawCode));
        $userToken->setCreatedAt(time());
        $userToken->save();

        return $rawCode;
    }
}
