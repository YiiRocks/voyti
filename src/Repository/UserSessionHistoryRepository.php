<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\UserSessionHistory;

final class UserSessionHistoryRepository
{
    /**
     * @return UserSessionHistory[]
     *
     * @psalm-return list<UserSessionHistory>
     */
    public function findAllSessionHistory(): array
    {
        /** @var list<UserSessionHistory> $sessions */
        $sessions = UserSessionHistory::query()->all();
        return $sessions;
    }

    /**
     * @return UserSessionHistory[]
     *
     * @psalm-return list<UserSessionHistory>
     */
    public function findByUserId(int $userId): array
    {
        /** @var list<UserSessionHistory> $sessions */
        $sessions = UserSessionHistory::query()->where(['user_id' => $userId])->all();
        return $sessions;
    }

    /**
     * @return (array|object)[]
     *
     * @psalm-return array<array|object>
     */
    public function search(array $filters = []): array
    {
        $query = UserSessionHistory::query();
        if (!empty($filters['user_id'])) {
            $query = $query->where(['user_id' => $filters['user_id']]);
        }
        if (!empty($filters['ip'])) {
            $query = $query->where(['like', 'ip', $filters['ip']]);
        }
        return $query->all();
    }
}
