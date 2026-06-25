<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Repository;

use YiiRocks\Voyti\Entity\UserProfile;

/** @extends BaseRepository<UserProfile> */
final class UserProfileRepository extends BaseRepository
{
    public function __construct()
    {
    }

    public function findByUserId(int $userId): ?UserProfile
    {
        /** @var ?UserProfile $userProfile */
        $userProfile = $this->findOne(UserProfile::class, ['user_id' => $userId]);
        return $userProfile;
    }
}
