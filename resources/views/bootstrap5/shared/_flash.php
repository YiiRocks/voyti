<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\FlashType;
use Yiisoft\Html\Html;
use Yiisoft\Session\Flash\FlashInterface;

/**
 * @var FlashInterface $flash
 */

$warning = (string) $flash->get(FlashType::WARNING);
if ($warning !== '') {
    echo Html::div($warning)->class('alert', 'alert-warning');
}

$message = (string) $flash->get(FlashType::SUCCESS);
if ($message !== '') {
    echo Html::div($message)->class('alert', 'alert-success');
}
