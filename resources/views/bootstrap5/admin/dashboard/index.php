<?php

declare(strict_types=1);

use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var array{
 *     userTotal: int,
 *     userBlocked: int,
 *     userUnconfirmed: int|null,
 *     roleCount: int,
 *     permissionCount: int,
 *     ruleCount: int,
 *     newRegistrations: array{oneDay: int, sevenDays: int, lifespan: int},
 *     activeSessions: array{oneDay: int, sevenDays: int, lifespan: int},
 *     rememberLifespanDays: int,
 *     recentAuditLogs: list<array{createdAt: string, action: string, targetLabel: string}>,
 * } $stats
 * @var ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var FlashInterface $flash
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.dashboard.title', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['url' => $url, 'translator' => $translator]);
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_flash', ['flash' => $flash]);

echo Html::H1($translator->translate('voyti.view.dashboard.title', category: 'voyti'));

$tiles = [
    ['label' => 'voyti.view.dashboard.users_total', 'value' => $stats['userTotal'], 'href' => 'voyti/admin-users', 'border' => 'border-primary'],
    ['label' => 'voyti.view.dashboard.users_blocked', 'value' => $stats['userBlocked'], 'href' => 'voyti/admin-users', 'border' => 'border-danger'],
];
if ($stats['userUnconfirmed'] !== null) {
    $tiles[] = ['label' => 'voyti.view.dashboard.users_unconfirmed', 'value' => $stats['userUnconfirmed'], 'href' => 'voyti/admin-users', 'border' => 'border-warning'];
}
$tiles[] = ['label' => 'voyti.view.dashboard.roles', 'value' => $stats['roleCount'], 'href' => 'voyti/admin-rbac-roles', 'border' => 'border-secondary'];
$tiles[] = ['label' => 'voyti.view.dashboard.permissions', 'value' => $stats['permissionCount'], 'href' => 'voyti/admin-rbac-permissions', 'border' => 'border-secondary'];
$tiles[] = ['label' => 'voyti.view.dashboard.rules', 'value' => $stats['ruleCount'], 'href' => 'voyti/admin-rbac-rules', 'border' => 'border-secondary'];

echo Html::div()->class('row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3 mb-4')->open();
foreach ($tiles as $tile) {
    echo Html::div()->class('col')->open();
    echo Html::a()
        ->href($url->generate($tile['href']))
        ->class('text-decoration-none')
        ->open();
    echo Html::div()->class('card h-100 text-center ' . $tile['border'])->open();
    echo Html::div()->class('card-body')->open();
    echo Html::div((string) $tile['value'])->class('fs-2 fw-bold');
    echo Html::div($translator->translate($tile['label'], category: 'voyti'))->class('text-muted small');
    echo Html::div()->close();
    echo Html::div()->close();
    echo Html::a()->close();
    echo Html::div()->close();
}
echo Html::div()->close();

$trendWidgets = [
    ['title' => 'voyti.view.dashboard.new_registrations', 'data' => $stats['newRegistrations']],
    ['title' => 'voyti.view.dashboard.active_sessions', 'data' => $stats['activeSessions']],
];

echo Html::div()->class('row row-cols-1 row-cols-md-2 g-3 mb-4')->open();
foreach ($trendWidgets as $widget) {
    $periods = [
        ['label' => 'voyti.view.dashboard.last_1d', 'value' => $widget['data']['oneDay'], 'params' => []],
        ['label' => 'voyti.view.dashboard.last_7d', 'value' => $widget['data']['sevenDays'], 'params' => []],
        ['label' => 'voyti.view.dashboard.last_lifespan', 'value' => $widget['data']['lifespan'], 'params' => ['days' => $stats['rememberLifespanDays']]],
    ];

    echo Html::div()->class('col')->open();
    echo Html::div()->class('card h-100')->open();
    echo Html::div()->class('card-header')->open();
    echo Html::H2($translator->translate($widget['title'], category: 'voyti'))->class('h5 mb-0');
    echo Html::div()->close();
    echo Html::div()->class('card-body')->open();
    echo Html::div()->class('row row-cols-3 text-center g-2')->open();
    foreach ($periods as $period) {
        echo Html::div()->class('col')->open();
        echo Html::div((string) $period['value'])->class('fs-3 fw-bold');
        echo Html::div($translator->translate($period['label'], $period['params'], category: 'voyti'))->class('text-muted small');
        echo Html::div()->close();
    }
    echo Html::div()->close();
    echo Html::div()->close();
    echo Html::div()->close();
    echo Html::div()->close();
}
echo Html::div()->close();

echo Html::div()->class('card')->open();
echo Html::div()->class('card-header')->open();
echo Html::H2($translator->translate('voyti.view.dashboard.recent_activity', category: 'voyti'))->class('h5 mb-0');
echo Html::div()->close();
echo Html::div()->class('card-body')->open();

if ($stats['recentAuditLogs'] === []) {
    echo Html::p($translator->translate('voyti.view.dashboard.no_recent_activity', category: 'voyti'))->class('text-muted mb-0');
} else {
    foreach ($stats['recentAuditLogs'] as $log) {
        echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
        echo Html::div($log['createdAt'])->class('col-3 col-md-2 text-muted small');
        echo Html::div($log['action'])->class('col-9 col-md-4 text-break');
        echo Html::div($log['targetLabel'])->class('col-12 col-md-6 text-break small text-muted');
        echo Html::div()->close();
    }
    echo Html::div()->class('mt-3')->open();
    echo Html::a($translator->translate('voyti.view.audit_log.title', category: 'voyti'), $url->generate('voyti/admin-audit-log'))->class('btn btn-outline-secondary btn-sm');
    echo Html::div()->close();
}

echo Html::div()->close();
echo Html::div()->close();

echo Html::div()->close();
