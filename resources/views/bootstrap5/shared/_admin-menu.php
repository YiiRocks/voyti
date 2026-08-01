<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use YiiRocks\Voyti\Widget\SwitchIdentityWidget;
use Yiisoft\Html\Html;

/**
 * @var MenuViewData $menu
 * @var string $csrf
 */

echo SwitchIdentityWidget::widget();

echo Html::ul()->class('nav nav-tabs mb-4')->open();
foreach ($menu->items as $item) {
    echo Html::li()->class($item->alignEnd ? 'nav-item ms-auto' : 'nav-item')->open();

    if ($item->routeName === 'voyti/session-logout') {
        echo Html::form()
            ->post($item->url)
            ->csrf($csrf)
            ->open();
        echo Html::submitButton($item->label)->class('nav-link', 'btn', 'btn-link');
        echo Html::form()->close();
    } else {
        echo Html::a($item->label, $item->url)->class('nav-link');
    }

    echo Html::li()->close();
}
echo Html::ul()->close();
