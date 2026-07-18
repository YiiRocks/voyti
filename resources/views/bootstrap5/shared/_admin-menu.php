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
        Html::a($translator->translate('voyti.view.dashboard.title', category: 'voyti'), $url->generate('voyti/admin'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
    Html::li(
        Html::a($translator->translate('voyti.view.admin.title', category: 'voyti'), $url->generate('voyti/admin-users'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
    Html::li(
        Html::a($translator->translate('voyti.view.permission.title', category: 'voyti'), $url->generate('voyti/admin-rbac-permissions'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
    Html::li(
        Html::a($translator->translate('voyti.view.role.title', category: 'voyti'), $url->generate('voyti/admin-rbac-roles'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
    Html::li(
        Html::a($translator->translate('voyti.view.rule.title', category: 'voyti'), $url->generate('voyti/admin-rbac-rules'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
    Html::li(
        Html::a($translator->translate('voyti.view.audit_log.title', category: 'voyti'), $url->generate('voyti/admin-audit-log'))->class('nav-link'),
        ['class' => 'nav-item'],
    ),
    Html::li(
        Html::a($translator->translate('voyti.menu.logout', category: 'voyti'), $url->generate('voyti/session-logout'))->class('nav-link'),
        ['class' => 'nav-item ms-auto'],
    ),
];

echo Html::ul()
    ->class('nav nav-tabs mb-4')
    ->items(...$adminMenuItems);
