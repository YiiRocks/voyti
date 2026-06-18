<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Event\User;

use YiiRocks\Voyti\Entity\Profile;

final class ProfileEvent
{
    public function __construct(
        private readonly Profile $profile,
    ) {
    }

    public function getProfile(): Profile
    {
        return $this->profile;
    }
}
