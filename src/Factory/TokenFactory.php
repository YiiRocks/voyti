<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Factory;

use YiiRocks\Voyti\Entity\Token;
use YiiRocks\Voyti\Repository\TokenRepository;

final class TokenFactory
{
    public function __construct(
        private readonly TokenRepository $tokenRepository,
    ) {
    }

    public function makeConfirmationToken(int $userId): Token
    {
        return $this->make($userId, Token::TYPE_CONFIRMATION);
    }

    public function makeConfirmNewMailToken(int $userId): Token
    {
        return $this->make($userId, Token::TYPE_CONFIRM_NEW_EMAIL);
    }

    public function makeConfirmOldMailToken(int $userId): Token
    {
        return $this->make($userId, Token::TYPE_CONFIRM_OLD_EMAIL);
    }

    public function makeRecoveryToken(int $userId): Token
    {
        return $this->make($userId, Token::TYPE_RECOVERY);
    }

    private function make(int $userId, int $type): Token
    {
        $token = new Token();
        $token->setUserId($userId);
        $token->setType($type);
        $token->setCode($this->generateCode());
        $token->setCreatedAt(time());
        $token->save();
        return $token;
    }

    private function generateCode(): string
    {
        return bin2hex(random_bytes(16));
    }
}
