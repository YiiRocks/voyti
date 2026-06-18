<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use Yiisoft\ActiveRecord\ActiveQuery;

trait RepositoryTrait
{
    protected function findOne(string $class, array $condition): ?object
    {
        return (new ActiveQuery($class))
            ->where($condition)
            ->one() ?: null;
    }

    protected function findAll(string $class, array $condition = []): array
    {
        $query = new ActiveQuery($class);
        if (!empty($condition)) {
            $query = $query->where($condition);
        }
        return $query->all();
    }
}
