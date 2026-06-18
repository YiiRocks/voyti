<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use Yiisoft\ActiveRecord\ActiveQuery;
use YiiRocks\Voyti\Entity\SessionHistory;

final class SessionHistoryRepository
{
    use RepositoryTrait;

    public function findByUserId(int $userId): array
    {
        return $this->findAll(SessionHistory::class, ['user_id' => $userId]);
    }

    public function findAllSessionHistory(): array
    {
        return (new ActiveQuery(SessionHistory::class))->all();
    }

    public function search(array $filters = []): array
    {
        $query = new ActiveQuery(SessionHistory::class);
        if (!empty($filters['user_id'])) {
            $query = $query->where(['user_id' => $filters['user_id']]);
        }
        if (!empty($filters['ip'])) {
            $query = $query->where(['like', 'ip', $filters['ip']]);
        }
        return $query->all();
    }
}
