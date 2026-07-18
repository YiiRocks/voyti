<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

/**
 * ActiveRecord for the `user_password_history` table: past password hashes kept to enforce
 * password-reuse restrictions.
 */
final class UserPasswordHistory extends ActiveRecord
{
    use PrivatePropertiesTrait;

    private int $created_at = 0;
    private string $password_hash = '';
    private int $user_id = 0;

    public static function deleteAllByUserId(int $userId): void
    {
        (new self())->deleteAll(['user_id' => $userId]);
    }

    /**
     * @psalm-return list<UserPasswordHistory>
     */
    public static function findByUserId(int $userId): array
    {
        /** @var list<UserPasswordHistory> $history */
        $history = self::query()->where(['user_id' => $userId])->orderBy(['created_at' => SORT_DESC])->all();
        return $history;
    }

    public function getCreatedAt(): int
    {
        return $this->created_at;
    }

    public function getPasswordHash(): string
    {
        return $this->password_hash;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    /**
     * @psalm-return list{'user_id', 'password_hash'}
     */
    #[\Override]
    public function primaryKey(): array
    {
        return ['user_id', 'password_hash'];
    }

    public function setCreatedAt(int $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->password_hash = $passwordHash;
    }

    public function setUserId(int $userId): void
    {
        $this->user_id = $userId;
    }
}
