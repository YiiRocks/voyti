<?php

declare(strict_types=1);

use YiiRocks\ToastBootstrap5\ToastInterface;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use Yiisoft\Html\Html;
use Yiisoft\View\WebView;

/**
 * Renders voyti's flash messages as Bootstrap 5 toasts (if yiirocks/toast-bootstrap5 is installed)
 * or alerts (fallback). Expects $flash and $toast to be injected.
 *
 * @var FlashViewData $flash
 * @var ToastInterface|null $toast
 * @var WebView $this
 */

// Toast-bootstrap5 installed: render as toasts
if (isset($toast) && $toast instanceof ToastInterface) {
    echo $toast->render($this);
    return;
}

// Fallback: render as alerts
if ($flash->warning !== null) {
    echo Html::div($flash->warning)->class('alert', 'alert-warning');
}

if ($flash->success !== null) {
    echo Html::div($flash->success)->class('alert', 'alert-success');
}
