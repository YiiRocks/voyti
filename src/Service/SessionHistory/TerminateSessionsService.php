<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\UserSessionHistory;

use Yiisoft\Db\Connection\ConnectionInterface;

final class TerminateSessionsService implements TerminateSessionsServiceInterface
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {
    }

    #[\Override]
    public function run(): void
    {
        // DB-backed session termination
    }
}
