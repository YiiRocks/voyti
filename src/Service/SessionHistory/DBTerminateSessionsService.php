<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\SessionHistory;

use Yiisoft\Db\Connection\ConnectionInterface;

final class DBTerminateSessionsService
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {
    }

    public function run(int $userId): void
    {
        $this->db->createCommand('DELETE FROM {{%session_history}} WHERE user_id = :userId', [
            ':userId' => $userId,
        ])->execute();
    }
}
