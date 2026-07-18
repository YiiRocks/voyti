<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\User;

use YiiRocks\Voyti\Model\UserProfile;

/**
 * Dispatched after a user's profile is saved, carrying the updated `UserProfile`.
 */
final readonly class UserProfileEvent
{
    public function __construct(
        private UserProfile $userProfile,
    ) {}

    public function getProfile(): UserProfile
    {
        return $this->userProfile;
    }
}
