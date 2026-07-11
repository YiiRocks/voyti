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

    /**
     * @return UserSessionHistory[]
     *
     * @psalm-return list<UserSessionHistory>
     */
    public static function findAllSessionHistory(): array
    {
        /** @var list<UserSessionHistory> $sessions */
        $sessions = self::query()->all();
        return $sessions;
    }

    /**
     * @return UserSessionHistory[]
     *
     * @psalm-return list<UserSessionHistory>
     */
    public static function findByUserId(int $userId): array
    {
        /** @var list<UserSessionHistory> $sessions */
        $sessions = self::query()->where(['user_id' => $userId])->all();
        return $sessions;
    }

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

    /**
     * @psalm-return list{'user_id', 'session_id'}
     */
    #[\Override]
    public function primaryKey(): array
    {
        return ['user_id', 'session_id'];
    }

    /**
     * @return (array|object)[]
     *
     * @psalm-return array<array|object>
     */
    public static function search(array $filters = []): array
    {
        $query = self::query();
        if (!empty($filters['user_id'])) {
            $query = $query->where(['user_id' => $filters['user_id']]);
        }
        if (!empty($filters['ip'])) {
            $query = $query->where(['like', 'ip', $filters['ip']]);
        }
        return $query->all();
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

    /**
     * @return string
     *
     * @psalm-return '{{%user_session_history}}'
     */
    #[\Override]
    public function tableName(): string
    {
        return '{{%user_session_history}}';
    }
}
