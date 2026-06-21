<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\UserSessionHistory;

interface TerminateSessionsServiceInterface
{
    public function run(): void;
}
