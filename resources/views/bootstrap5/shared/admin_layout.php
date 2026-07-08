<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var string $content
 */

echo Html::div($content)->class('container-fluid p-3 bg-light rounded');
