<?php

declare(strict_types=1);

use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

$items = [
    Html::li(
        Html::a($translator->translate('voyti.menu.userProfile', category: 'voyti'), $url->generate('voyti/settings'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
    Html::li(
        Html::a($translator->translate('voyti.menu.account', category: 'voyti'), $url->generate('voyti/settings-account'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
    Html::li(
        Html::a($translator->translate('voyti.menu.networks', category: 'voyti'), $url->generate('voyti/settings-networks'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
];

if ($config->enableTwoFactorAuthentication) {
    $items[] = Html::li(
        Html::a($translator->translate('voyti.menu.two_factor', category: 'voyti'), $url->generate('voyti/settings-two-factor'))->class('nav-link'),
        ['class' => 'nav-item'],
    );
}

if ($config->enableGdprCompliance) {
    $items[] = Html::li(
        Html::a($translator->translate('voyti.view.settings.privacy', category: 'voyti'), $url->generate('voyti/settings-privacy'))->class('nav-link'),
        ['class' => 'nav-item'],
    );
}

echo Html::ul()
    ->class('nav nav-pills mb-4')
    ->items(...$items);
