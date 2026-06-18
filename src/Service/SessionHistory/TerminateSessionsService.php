<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\SessionHistory;

use Yiisoft\Db\Connection\ConnectionInterface;

final class TerminateSessionsService implements TerminateSessionsServiceInterface
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {
    }

    public function run(): void
    {
        // DB-backed session termination
    }
}
