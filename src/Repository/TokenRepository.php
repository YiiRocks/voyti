<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\Token;

/** @extends BaseRepository<Token> */
final class TokenRepository extends BaseRepository
{
    public function __construct()
    {
    }

    public function deleteAllByUserId(int $userId): void
    {
        $this->deleteAll(Token::class, ['user_id' => $userId]);
    }

    public function findByCodeAndType(string $code, int $type): ?Token
    {
        return $this->findOne(Token::class, ['code' => $code, 'type' => $type]);
    }

    /**
     * @return Token[]
     */
    public function findByUserId(int $userId): array
    {
        return $this->findAll(Token::class, ['user_id' => $userId]);
    }

    public function findByUserIdAndCode(int $userId, string $code): ?Token
    {
        return $this->findOne(Token::class, ['user_id' => $userId, 'code' => $code]);
    }

    public function findByUserIdAndCodeAndType(int $userId, string $code, int $type): ?Token
    {
        return $this->findOne(Token::class, ['user_id' => $userId, 'code' => $code, 'type' => $type]);
    }

    public function findByUserIdTypeAndCode(int $userId, int $type, string $code): ?Token
    {
        return $this->findByUserIdAndCodeAndType($userId, $code, $type);
    }
}
