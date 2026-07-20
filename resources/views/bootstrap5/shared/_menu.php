<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Shared\MenuLinkViewData;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Html\Html;

/**
 * @var MenuViewData $menu
 */

$items = array_map(
    static fn(MenuLinkViewData $item) => Html::li(
        Html::a($item->label, $item->url)->class('nav-link'),
        ['class' => $item->alignEnd ? 'nav-item ms-auto' : 'nav-item'],
    ),
    $menu->items,
);

echo Html::ul()
    ->class('nav nav-tabs mb-4')
    ->items(...$items);
