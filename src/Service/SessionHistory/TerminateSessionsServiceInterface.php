<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Service\SessionHistory;

interface TerminateSessionsServiceInterface
{
    public function run(): void;
}
