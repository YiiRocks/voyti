<?php

declare(strict_types=1);

use Yiisoft\View\ViewInterface;
use Yiisoft\View\WebView;

/** @var array $params */
return [
    ViewInterface::class => WebView::class,

    'routes' => require __DIR__ . '/routes.php',
];
