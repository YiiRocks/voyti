<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use Yiisoft\ActiveRecord\ActiveRecordFactory;
use YiiRocks\Voyti\Entity\SessionHistory;

final class SessionHistoryRepository
{
    use RepositoryTrait;

    public function __construct(ActiveRecordFactory $arFactory)
    {
        $this->arFactory = $arFactory;
    }

    public function findByUserId(int $userId): array
    {
        return $this->findAll(SessionHistory::class, ['user_id' => $userId]);
    }

    public function findAllSessionHistory(): array
    {
        $query = $this->arFactory->createQueryTo(SessionHistory::class);
        return $query->all();
    }

    public function search(array $filters = []): array
    {
        $query = $this->arFactory->createQueryTo(SessionHistory::class);
        if (!empty($filters['user_id'])) {
            $query = $query->where(['user_id' => $filters['user_id']]);
        }
        if (!empty($filters['ip'])) {
            $query = $query->where(['like', 'ip', $filters['ip']]);
        }
        return $query->all();
    }
}
