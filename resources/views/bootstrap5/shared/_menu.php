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
        Html::a($translator->translate('voyti.menu.userProfile', category: 'voyti'), $url->generate('voyti/profile-update'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
    Html::li(
        Html::a($translator->translate('voyti.menu.account', category: 'voyti'), $url->generate('voyti/account-update'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
    Html::li(
        Html::a($translator->translate('voyti.menu.networks', category: 'voyti'), $url->generate('voyti/social-network'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
];

if ($config->enableSessionHistory) {
    $items[] = Html::li(
        Html::a($translator->translate('voyti.menu.sessions', category: 'voyti'), $url->generate('voyti/account-sessions'))->class('nav-link'),
        ['class' => 'nav-item'],
    );
}

if ($config->enableTwoFactorAuthentication) {
    $items[] = Html::li(
        Html::a($translator->translate('voyti.menu.two_factor', category: 'voyti'), $url->generate('voyti/two-factor'))->class('nav-link'),
        ['class' => 'nav-item'],
    );
}

if ($config->enableGdprCompliance || $config->allowAccountDelete) {
    $items[] = Html::li(
        Html::a($translator->translate('voyti.view.settings.privacy', category: 'voyti'), $url->generate('voyti/privacy'))->class('nav-link'),
        ['class' => 'nav-item'],
    );
}

$items[] = Html::li(
    Html::a($translator->translate('voyti.menu.logout', category: 'voyti'), $url->generate('voyti/session-logout'))->class('nav-link'),
    ['class' => 'nav-item ms-auto'],
);

echo Html::ul()
    ->class('nav nav-tabs mb-4')
    ->items(...$items);
