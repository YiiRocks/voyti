<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    $extPath = PHP_EXTENSION_DIR . '/pdo_sqlite.' . PHP_SHLIB_SUFFIX;
    if (is_file($extPath) && function_exists('dl')) {
        @dl('pdo_sqlite');
    }
}

require_once __DIR__ . '/../vendor/autoload.php';
