<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\UserSessionHistory;

use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class TerminateSessionsService implements TerminateSessionsServiceInterface
{
    public function __construct(
        private ConnectionInterface $db,
    ) {
    }

    #[\Override]
    public function run(): void
    {
        // DB-backed session termination
    }
}
