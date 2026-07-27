<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Shared\MenuLinkViewData;
use YiiRocks\Voyti\ViewData\Shared\MenuViewData;
use Yiisoft\Html\Html;

/**
 * @var MenuViewData $menu
 * @var string $csrf
 */

if ($menu->switchedBannerMessage !== null) {
    echo Html::div()->class('alert alert-warning d-flex justify-content-between align-items-center')->open();
    echo Html::span($menu->switchedBannerMessage);
    echo Html::form()
        ->post($menu->switchIdentityRestoreUrl)
        ->csrf($csrf)
        ->open();
    echo Html::submitButton($menu->switchIdentityRestoreButtonLabel)->class('btn', 'btn-warning', 'btn-sm');
    echo Html::form()->close();
    echo Html::div()->close();
}

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
