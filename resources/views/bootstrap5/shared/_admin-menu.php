<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

$adminMenuItems = [
    Html::li(
        Html::a($translator->translate('voyti.view.admin.title', category: 'voyti'), $url->generate('voyti/admin'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
    Html::li(
        Html::a($translator->translate('voyti.view.permission.title', category: 'voyti'), $url->generate('voyti/permissions'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
    Html::li(
        Html::a($translator->translate('voyti.view.role.title', category: 'voyti'), $url->generate('voyti/roles'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
    Html::li(
        Html::a($translator->translate('voyti.view.rule.title', category: 'voyti'), $url->generate('voyti/rules'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
    Html::li(
        Html::a($translator->translate('voyti.menu.logout', category: 'voyti'), $url->generate('voyti/logout'))->class('nav-link'),
        ['class' => 'nav-item ms-auto'],
    ),
];

echo Html::ul()
    ->class('nav nav-tabs mb-4')
    ->items(...$adminMenuItems);
