<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Entity;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

final class UserSessionHistory extends ActiveRecord
{
    use PrivatePropertiesTrait;

    private int $created_at = 0;
    private ?string $ip = null;
    private string $session_id = '';
    private int $updated_at = 0;
    private ?string $user_agent = null;
    private int $user_id = 0;

    public function getCreatedAt(): int
    {
        return $this->created_at;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getSessionId(): string
    {
        return $this->session_id;
    }

    public function getUpdatedAt(): int
    {
        return $this->updated_at;
    }

    public function getUserAgent(): ?string
    {
        return $this->user_agent;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setCreatedAt(int $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    public function setIp(?string $ip): void
    {
        $this->ip = $ip;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->session_id = $sessionId;
    }

    public function setUpdatedAt(int $updatedAt): void
    {
        $this->updated_at = $updatedAt;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->user_agent = $userAgent;
    }

    public function setUserId(int $userId): void
    {
        $this->user_id = $userId;
    }

    #[\Override]
    public function tableName(): string
    {
        return '{{%user_session_history}}';
    }
}
