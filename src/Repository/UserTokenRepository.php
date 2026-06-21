<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\UserToken;

/** @extends BaseRepository<UserToken> */
final class UserTokenRepository extends BaseRepository
{
    public function __construct()
    {
    }

    public function deleteAllByUserId(int $userId): void
    {
        $this->deleteAll(UserToken::class, ['user_id' => $userId]);
    }

    public function findByCodeAndType(string $code, int $type): ?UserToken
    {
        return $this->findOne(UserToken::class, ['code' => $code, 'type' => $type]);
    }

    /**
     * @return UserToken[]
     */
    public function findByUserId(int $userId): array
    {
        return $this->findAll(UserToken::class, ['user_id' => $userId]);
    }

    public function findByUserIdAndCode(int $userId, string $code): ?UserToken
    {
        return $this->findOne(UserToken::class, ['user_id' => $userId, 'code' => $code]);
    }

    public function findByUserIdAndCodeAndType(int $userId, string $code, int $type): ?UserToken
    {
        return $this->findOne(UserToken::class, ['user_id' => $userId, 'code' => $code, 'type' => $type]);
    }

    public function findByUserIdTypeAndCode(int $userId, int $type, string $code): ?UserToken
    {
        return $this->findByUserIdAndCodeAndType($userId, $code, $type);
    }
}
