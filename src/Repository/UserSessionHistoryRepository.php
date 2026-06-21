<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\UserSessionHistory;

/** @extends BaseRepository<UserSessionHistory> */
final class UserSessionHistoryRepository extends BaseRepository
{
    public function findAllSessionHistory(): array
    {
        return $this->findAll(UserSessionHistory::class);
    }

    public function findByUserId(int $userId): array
    {
        return $this->findAll(UserSessionHistory::class, ['user_id' => $userId]);
    }

    public function search(array $filters = []): array
    {
        $query = $this->query(UserSessionHistory::class);
        if (!empty($filters['user_id'])) {
            $query = $query->where(['user_id' => $filters['user_id']]);
        }
        if (!empty($filters['ip'])) {
            $query = $query->where(['like', 'ip', $filters['ip']]);
        }
        return $query->all();
    }
}
