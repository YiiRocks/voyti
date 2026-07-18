<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

/**
 * ActiveRecord for the `user_token` table: a one-time code used for email confirmation, password
 * recovery, email change confirmation, or API access, distinguished by the `TYPE_*` constants and
 * expiring per {@see self::isExpired()}.
 */
final class UserToken extends ActiveRecord
{
    use PrivatePropertiesTrait;
    public const int TYPE_API_ACCESS = 4;
    public const int TYPE_CONFIRM_NEW_EMAIL = 2;
    public const int TYPE_CONFIRM_OLD_EMAIL = 3;
    public const int TYPE_CONFIRMATION = 0;
    public const int TYPE_RECOVERY = 1;
    private string $code = '';
    private int $created_at = 0;
    private int $type = 0;
    private int $user_id = 0;

    public static function deleteAllByUserId(int $userId): void
    {
        (new self())->deleteAll(['user_id' => $userId]);
    }

    public static function deleteAllByUserIdAndType(int $userId, int $type): void
    {
        (new self())->deleteAll(['user_id' => $userId, 'type' => $type]);
    }

    public static function findByCodeAndType(string $code, int $type): ?UserToken
    {
        /** @var ?UserToken $token */
        $token = self::query()->where(['code' => $code, 'type' => $type])->one();
        return $token;
    }

    /**
     * @psalm-return list<UserToken>
     */
    public static function findByUserId(int $userId): array
    {
        /** @var list<UserToken> $tokens */
        $tokens = self::query()->where(['user_id' => $userId])->all();
        return $tokens;
    }

    public static function findByUserIdAndCode(int $userId, string $code): ?UserToken
    {
        /** @var ?UserToken $token */
        $token = self::query()->where(['user_id' => $userId, 'code' => $code])->one();
        return $token;
    }

    public static function findByUserIdAndCodeAndType(int $userId, string $code, int $type): ?UserToken
    {
        /** @var ?UserToken $token */
        $token = self::query()->where(['user_id' => $userId, 'code' => $code, 'type' => $type])->one();
        return $token;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getCreatedAt(): int
    {
        return $this->created_at;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getUser(): ?User
    {
        /** @var ?User */
        return $this->hasOne(User::class, ['id' => 'user_id'])->one();
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function isExpired(?int $lifespan = null): bool
    {
        if ($lifespan === null) {
            $lifespan = match ($this->type) {
                self::TYPE_RECOVERY => 21600,
                default => 86400,
            };
        }

        return (time() - $this->created_at) > $lifespan;
    }

    /**
     * @return string[]
     *
     * @psalm-return list{'user_id', 'code', 'type'}
     */
    #[\Override]
    public function primaryKey(): array
    {
        return ['user_id', 'code', 'type'];
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function setCreatedAt(int $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function setUserId(int $userId): void
    {
        $this->user_id = $userId;
    }
}
