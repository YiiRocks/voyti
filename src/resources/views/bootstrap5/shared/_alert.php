<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var string $type
 * @var string $message
 */

echo Html::div()->class('alert alert-' . Html::encode($type) . ' alert-dismissible fade show shadow-sm')->attribute('role', 'alert')->open();
    echo Html::encode($message);
    echo Html::button('')->class('btn-close')->attribute('data-bs-dismiss', 'alert')->attribute('aria-label', 'Close');
echo Html::div()->close();
