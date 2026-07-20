<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Shared\SocialConnectViewData;
use Yiisoft\Html\Html;

/**
 * @var SocialConnectViewData $connect
 */

if ($connect->providers !== []) {
    echo Html::div()->class('btn-group')->open();
    foreach ($connect->providers as $provider) {
        echo Html::a($provider->title, $provider->url)->class('btn btn-primary');
    }
    echo Html::div()->close();
}
