<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * @template T of ActiveRecordInterface
 */
abstract class BaseRepository
{
    /**
     * @param class-string $class
     *
     * @psalm-return int<0, max>|string
     */
    protected function count(string $class, array $condition = []): int|string
    {
        $query = new ActiveQuery($class);
        if (!empty($condition)) {
            $query = $query->where($condition);
        }
        return $query->count();
    }

    protected function delete(ActiveRecordInterface $model): void
    {
        $model->delete();
    }

    /**
     * @param class-string $class
     */
    protected function deleteAll(string $class, array $condition): void
    {
        $query = new ActiveQuery($class);
        $query->where($condition)->delete();
    }

    /**
     * @param class-string $class
     * @return array<array-key, mixed>
     */
    protected function findAll(string $class, array $condition = []): array
    {
        $query = new ActiveQuery($class);
        if (!empty($condition)) {
            $query = $query->where($condition);
        }
        return $query->all();
    }

    /**
     * @template TEntity of ActiveRecordInterface
     *
     * @param class-string<TEntity> $class
     *
     * @return ActiveRecordInterface|array|null
     */
    protected function findOne(string $class, array $condition): array|ActiveRecordInterface|null
    {
        return (new ActiveQuery($class))
            ->where($condition)
            ->one() ?: null;
    }

    /**
     * @param class-string $class
     */
    protected function query(string $class): ActiveQuery
    {
        return new ActiveQuery($class);
    }

    protected function save(ActiveRecordInterface $model): bool
    {
        return $model->save();
    }
}
