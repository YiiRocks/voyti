<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use Yiisoft\Html\Html;

/**
 * @var FlashViewData $flash
 */

if ($flash->warning !== null) {
    echo Html::div($flash->warning)->class('alert', 'alert-warning');
}

if ($flash->success !== null) {
    echo Html::div($flash->success)->class('alert', 'alert-success');
}
