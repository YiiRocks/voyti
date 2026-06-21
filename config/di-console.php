<?php

declare(strict_types=1);

use Yiisoft\Db\Migration\Service\MigrationService;

return [
    MigrationService::class => [
        'setSourcePaths()' => [[dirname(__DIR__) . '/migrations']],
        'setNewMigrationPath()' => [dirname(__DIR__) . '/migrations'],
    ],
];
