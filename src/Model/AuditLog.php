<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Model;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

final class AuditLog extends ActiveRecord
{
    use PrivatePropertiesTrait;

    private string $action = '';
    private ?int $actor_user_id = null;
    private ?string $context = null;
    private int $created_at = 0;
    private ?int $id = null;
    private ?string $target_name = null;
    private ?int $target_user_id = null;

    public function getAction(): string
    {
        return $this->action;
    }

    public function getActorUserId(): ?int
    {
        return $this->actor_user_id;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function getCreatedAt(): int
    {
        return $this->created_at;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTargetName(): ?string
    {
        return $this->target_name;
    }

    public function getTargetUserId(): ?int
    {
        return $this->target_user_id;
    }

    /**
     * @psalm-return list{'id'}
     */
    #[\Override]
    public function primaryKey(): array
    {
        return ['id'];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public static function search(array $filters = []): ActiveQueryInterface
    {
        $query = self::query()->orderBy(['created_at' => SORT_DESC]);
        if (!empty($filters['actor_user_id'])) {
            $query = $query->andWhere(['actor_user_id' => $filters['actor_user_id']]);
        }
        if (!empty($filters['target_user_id'])) {
            $query = $query->andWhere(['target_user_id' => $filters['target_user_id']]);
        }
        if (!empty($filters['action'])) {
            $query = $query->andWhere(['like', 'action', $filters['action']]);
        }
        return $query;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function setActorUserId(?int $actorUserId): void
    {
        $this->actor_user_id = $actorUserId;
    }

    public function setContext(?string $context): void
    {
        $this->context = $context;
    }

    public function setCreatedAt(int $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    public function setTargetName(?string $targetName): void
    {
        $this->target_name = $targetName;
    }

    public function setTargetUserId(?int $targetUserId): void
    {
        $this->target_user_id = $targetUserId;
    }

    /**
     * @psalm-return '{{%audit_log}}'
     */
    #[\Override]
    public function tableName(): string
    {
        return '{{%audit_log}}';
    }
}
