<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

echo Html::div()->class('container-fluid p-3 bg-light rounded')->open();
    echo $content ?? '';
echo Html::div()->close();
