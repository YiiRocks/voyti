<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\UserToken;

final class UserTokenRepository
{
    public function deleteAllByUserId(int $userId): void
    {
        (new UserToken())->deleteAll(['user_id' => $userId]);
    }

    public function findByCodeAndType(string $code, int $type): ?UserToken
    {
        /** @var ?UserToken $token */
        $token = UserToken::query()->where(['code' => $code, 'type' => $type])->one();
        return $token;
    }

    /**
     * @psalm-return list<UserToken>
     */
    public function findByUserId(int $userId): array
    {
        /** @var list<UserToken> $tokens */
        $tokens = UserToken::query()->where(['user_id' => $userId])->all();
        return $tokens;
    }

    public function findByUserIdAndCode(int $userId, string $code): ?UserToken
    {
        /** @var ?UserToken $token */
        $token = UserToken::query()->where(['user_id' => $userId, 'code' => $code])->one();
        return $token;
    }

    public function findByUserIdAndCodeAndType(int $userId, string $code, int $type): ?UserToken
    {
        /** @var ?UserToken $token */
        $token = UserToken::query()->where(['user_id' => $userId, 'code' => $code, 'type' => $type])->one();
        return $token;
    }

    public function findByUserIdTypeAndCode(int $userId, int $type, string $code): ?UserToken
    {
        return $this->findByUserIdAndCodeAndType($userId, $code, $type);
    }
}
