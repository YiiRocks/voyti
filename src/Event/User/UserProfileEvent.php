<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\User;

use YiiRocks\Voyti\Entity\UserProfile;

final class UserProfileEvent
{
    public function __construct(
        private readonly UserProfile $userProfile,
    ) {
    }

    public function getProfile(): UserProfile
    {
        return $this->userProfile;
    }
}
