<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Session\Flash\FlashInterface;

/**
 * @var FlashInterface $flash
 */

$message = $flash->get('success');
if (is_string($message) && $message !== '') {
    echo Html::div($message)->class('alert', 'alert-success');
}
