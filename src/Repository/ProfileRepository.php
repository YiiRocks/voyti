<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\Profile;

final class ProfileRepository
{
    public function __construct()
    {
    }

    public function findByUserId(int $userId): ?Profile
    {
        return Profile::query()->where(['user_id' => $userId])->one();
    }

    public function save(Profile $profile): void
    {
        $profile->save();
    }

    public function delete(Profile $profile): void
    {
        $profile->delete();
    }
}
