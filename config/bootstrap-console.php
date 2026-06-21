<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Yiisoft\Db\Migration\Service\MigrationService;

return [
    static function (ContainerInterface $container): void {
        $migrationService = $container->get(MigrationService::class);
        $migrationService->setSourcePaths([dirname(__DIR__) . '/migrations']);
        $migrationService->setNewMigrationPath(dirname(__DIR__) . '/migrations');
    },
];
