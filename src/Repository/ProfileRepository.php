<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\Profile;

final class ProfileRepository extends BaseRepository
{
    public function __construct()
    {
    }

    public function findByUserId(int $userId): array|\Yiisoft\ActiveRecord\ActiveRecordInterface|null
    {
        return $this->findOne(Profile::class, ['user_id' => $userId]);
    }
}
