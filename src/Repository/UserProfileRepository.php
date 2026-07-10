<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\UserProfile;

final class UserProfileRepository
{
    public function findByUserId(int $userId): ?UserProfile
    {
        /** @var ?UserProfile $userProfile */
        $userProfile = UserProfile::query()->where(['user_id' => $userId])->one();
        return $userProfile;
    }
}
