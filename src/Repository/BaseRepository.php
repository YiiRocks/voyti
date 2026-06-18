<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use Yiisoft\ActiveRecord\ActiveQuery;

abstract class BaseRepository
{

    protected function count(string $class, array $condition = []): int
    {
        $query = new ActiveQuery($class);
        if (!empty($condition)) {
            $query = $query->where($condition);
        }
        return $query->count();
    }

    protected function delete(array|object $model): void
    {
        $model->delete();
    }

    protected function deleteAll(string $class, array $condition): void
    {
        $query = new ActiveQuery($class);
        $query->where($condition)->delete();
    }
    protected function findAll(string $class, array $condition = []): array
    {
        $query = new ActiveQuery($class);
        if (!empty($condition)) {
            $query = $query->where($condition);
        }
        return $query->all();
    }

    protected function findOne(string $class, array $condition): ?object
    {
        return (new ActiveQuery($class))
            ->where($condition)
            ->one() ?: null;
    }

    protected function query(string $class): ActiveQuery
    {
        return new ActiveQuery($class);
    }

    protected function save(array|object $model): bool
    {
        return $model->save();
    }
}
