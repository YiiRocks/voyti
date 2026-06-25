<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\UserSessionHistory;

use YiiRocks\Voyti\Entity\UserSessionHistory;

final class TerminateUserSessionsService
{
    public function __construct()
    {
    }

    public function run(int $userId): void
    {
        (new UserSessionHistory())->deleteAll(['user_id' => $userId]);
    }
}
