<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\User;

use YiiRocks\Voyti\Model\UserProfile;

final readonly class UserProfileEvent
{
    public function __construct(
        private UserProfile $userProfile,
    ) {
    }

    public function getProfile(): UserProfile
    {
        return $this->userProfile;
    }
}
