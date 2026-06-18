<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use Yiisoft\ActiveRecord\ActiveRecordFactory;

trait RepositoryTrait
{
    protected ActiveRecordFactory $arFactory;

    protected function findOne(string $class, array $condition): ?object
    {
        $query = $this->arFactory->createQueryTo($class);
        foreach ($condition as $col => $val) {
            $query = $query->where([$col => $val]);
        }
        return $query->one() ?: null;
    }

    protected function findAll(string $class, array $condition = []): array
    {
        $query = $this->arFactory->createQueryTo($class);
        foreach ($condition as $col => $val) {
            $query = $query->where([$col => $val]);
        }
        return $query->all();
    }
}
