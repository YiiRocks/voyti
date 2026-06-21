<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Entity;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

final class UserToken extends ActiveRecord
{
    use PrivatePropertiesTrait;
    public const TYPE_CONFIRM_NEW_EMAIL = 2;
    public const TYPE_CONFIRM_OLD_EMAIL = 3;
    public const TYPE_CONFIRMATION = 0;
    public const TYPE_RECOVERY = 1;
    private string $code = '';
    private int $createdAt = 0;
    private int $type;

    private int $userId;

    public function getCode(): string
    {
        return $this->code;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getIsExpired(): bool
    {
        $lifespan = match ($this->type) {
            self::TYPE_CONFIRMATION => 86400,
            self::TYPE_RECOVERY => 21600,
            self::TYPE_CONFIRM_NEW_EMAIL, self::TYPE_CONFIRM_OLD_EMAIL => 86400,
            default => 86400,
        };

        return (time() - $this->createdAt) > $lifespan;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getUser(): ?User
    {
        /** @var ?User */
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    #[\Override]
    public function primaryKey(): array
    {
        return ['userId', 'code', 'type'];
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function setCreatedAt(int $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    #[\Override]
    public function tableName(): string
    {
        return '{{%user_token}}';
    }
}
