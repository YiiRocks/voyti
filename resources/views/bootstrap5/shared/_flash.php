<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Session\Flash\FlashInterface;

/**
 * @var FlashInterface $flash
 */

$warning = (string) $flash->get('warning');
if ($warning !== '') {
    echo Html::div($warning)->class('alert', 'alert-warning');
}

$message = (string) $flash->get('success');
if ($message !== '') {
    echo Html::div($message)->class('alert', 'alert-success');
}
