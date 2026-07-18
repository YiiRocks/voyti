<?php

declare(strict_types=1);

use Composer\InstalledVersions;

$rbacDbPath = InstalledVersions::getInstallPath('yiisoft/rbac-db');

return [
    'yiisoft/db-migration' => [
        'sourcePaths' => [
            dirname(__DIR__) . '/migrations',
            $rbacDbPath . '/migrations/items',
            $rbacDbPath . '/migrations/assignments',
        ],
    ],
];
