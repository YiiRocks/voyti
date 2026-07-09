<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Entity;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

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

    public function getCode(): string
    {
        return $this->code;
    }

    public function getCreatedAt(): int
    {
        return $this->created_at;
    }

    public function getIsExpired(?int $lifespan = null): bool
    {
        if ($lifespan === null) {
            $lifespan = match ($this->type) {
                self::TYPE_RECOVERY => 21600,
                default => 86400,
            };
        }

        return (time() - $this->created_at) > $lifespan;
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

    /**
     * @return string
     *
     * @psalm-return '{{%user_token}}'
     */
    #[\Override]
    public function tableName(): string
    {
        return '{{%user_token}}';
    }
}
