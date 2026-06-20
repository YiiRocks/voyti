<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Entity;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

final class SessionHistory extends ActiveRecord
{
    use PrivatePropertiesTrait;
    private int $createdAt = 0;
    private ?string $ip = null;
    private string $sessionId = '';
    private int $updatedAt = 0;
    private ?string $userAgent = null;
    private int $userId;

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    public function getUser(): ?User
    {
        /** @var ?User */
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    #[\Override]
    public function primaryKey(): array
    {
        return ['userId', 'sessionId'];
    }

    public function setCreatedAt(int $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setIp(?string $ip): void
    {
        $this->ip = $ip;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function setUpdatedAt(int $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    #[\Override]
    public function tableName(): string
    {
        return '{{%session_history}}';
    }
}
