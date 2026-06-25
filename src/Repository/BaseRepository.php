<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * @template TEntity of ActiveRecordInterface
 */
abstract class BaseRepository
{
    /**
     * @param class-string<TEntity> $class
     *
     * @psalm-return int<0, max>|string
     */
    protected function count(string $class, array $condition = []): int|string
    {
        $query = new ActiveQuery(new $class());
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
     * @param class-string<ActiveRecordInterface> $class
     */
    protected function deleteAll(string $class, array $condition): void
    {
        (new $class())->deleteAll($condition);
    }

    /**
     * @param class-string<TEntity> $class
     *
     * @psalm-return list<TEntity>
     */
    protected function findAll(string $class, array $condition = []): array
    {
        $query = new ActiveQuery(new $class());
        if (!empty($condition)) {
            $query = $query->where($condition);
        }
        /** @var list<TEntity> $rows */
        $rows = $query->all();
        return $rows;
    }

    /**
     * @param class-string<TEntity> $class
     *
     * @psalm-return TEntity|null
     */
    protected function findOne(string $class, array $condition): ?ActiveRecordInterface
    {
        /** @var TEntity|null $row */
        $row = (new ActiveQuery(new $class()))
            ->where($condition)
            ->one() ?: null;
        return $row;
    }

    /**
     * @param class-string<ActiveRecordInterface> $class
     */
    protected function query(string $class): ActiveQuery
    {
        return new ActiveQuery(new $class());
    }

    protected function save(ActiveRecordInterface $model): void
    {
        $model->save();
    }
}
