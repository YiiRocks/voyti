<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\Token;

final class TokenRepository
{
    public function __construct()
    {
    }

    public function findByUserIdAndCodeAndType(int $userId, string $code, int $type): ?Token
    {
        return Token::query()
            ->where(['user_id' => $userId, 'code' => $code, 'type' => $type])
            ->one();
    }

    public function findByUserIdTypeAndCode(int $userId, int $type, string $code): ?Token
    {
        return $this->findByUserIdAndCodeAndType($userId, $code, $type);
    }

    public function findByUserIdAndCode(int $userId, string $code): ?Token
    {
        return Token::query()
            ->where(['user_id' => $userId, 'code' => $code])
            ->one();
    }

    public function findByUserId(int $userId): array
    {
        return Token::query()->where(['user_id' => $userId])->all();
    }

    public function findByCodeAndType(string $code, int $type): ?Token
    {
        return Token::query()->where(['code' => $code, 'type' => $type])->one();
    }

    public function save(Token $token): void
    {
        $token->save();
    }

    public function delete(Token $token): void
    {
        $token->delete();
    }

    public function deleteAllByUserId(int $userId): void
    {
        $token = new Token();
        $token->deleteAll(['user_id' => $userId]);
    }
}
