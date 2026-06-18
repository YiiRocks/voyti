<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\SessionHistory;

use Yiisoft\ActiveRecord\ActiveQuery;
use YiiRocks\Voyti\Entity\SessionHistory;

final class TerminateUserSessionsService
{
    public function __construct()
    {
    }

    public function run(int $userId): void
    {
        (new ActiveQuery(SessionHistory::class))
            ->where(['user_id' => $userId])
            ->delete();
    }
}
