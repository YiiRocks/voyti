<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\UserSessionHistory;

use YiiRocks\Voyti\Entity\UserSessionHistory;
use Yiisoft\ActiveRecord\ActiveQuery;

final class TerminateUserSessionsService
{
    public function __construct()
    {
    }

    public function run(int $userId): void
    {
        (new ActiveQuery(UserSessionHistory::class))
            ->where(['user_id' => $userId])
            ->delete();
    }
}
