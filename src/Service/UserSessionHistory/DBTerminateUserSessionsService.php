<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\UserSessionHistory;

use Yiisoft\Db\Connection\ConnectionInterface;

final class DBTerminateUserSessionsService
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {
    }

    public function run(int $userId): void
    {
        $this->db->createCommand('DELETE FROM {{%user_session_history}} WHERE user_id = :userId', [
            ':userId' => $userId,
        ])->execute();
    }
}
