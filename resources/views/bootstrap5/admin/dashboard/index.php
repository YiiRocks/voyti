<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Admin\Dashboard\IndexViewData;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var IndexViewData $data
 * @var TranslatorInterface $translator
 * @var FlashViewData $flash
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.dashboard.title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['menu' => $data->menu]);
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_flash', ['flash' => $flash]);

echo Html::H1($translator->translate('voyti.view.dashboard.title'));

echo Html::div()->class('row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3 mb-4')->open();
foreach ($data->tiles as $tile) {
    echo Html::div()->class('col')->open();
    echo Html::a()
        ->href($tile->url)
        ->class('text-decoration-none')
        ->open();
    echo Html::div()->class('card h-100 text-center ' . $tile->borderClass)->open();
    echo Html::div()->class('card-body')->open();
    echo Html::div((string) $tile->value)->class('fs-2 fw-bold');
    echo Html::div($translator->translate($tile->labelKey))->class('text-muted small');
    echo Html::div()->close();
    echo Html::div()->close();
    echo Html::a()->close();
    echo Html::div()->close();
}
echo Html::div()->close();

echo Html::div()->class('row row-cols-1 row-cols-md-2 g-3 mb-4')->open();
foreach ($data->trendWidgets as $widget) {
    echo Html::div()->class('col')->open();
    echo Html::div()->class('card h-100')->open();
    echo Html::div()->class('card-header')->open();
    echo Html::H2($translator->translate($widget->titleKey))->class('h5 mb-0');
    echo Html::div()->close();
    echo Html::div()->class('card-body')->open();
    echo Html::div()->class('row row-cols-3 text-center g-2')->open();
    foreach ($widget->periods as $period) {
        echo Html::div()->class('col')->open();
        echo Html::div((string) $period->value)->class('fs-3 fw-bold');
        echo Html::div($translator->translate($period->labelKey, $period->params))->class('text-muted small');
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
echo Html::H2($translator->translate('voyti.view.dashboard.recent_activity'))->class('h5 mb-0');
echo Html::div()->close();
echo Html::div()->class('card-body')->open();

if ($data->recentAuditLogs === []) {
    echo Html::p($translator->translate('voyti.view.dashboard.no_recent_activity'))->class('text-muted mb-0');
} else {
    foreach ($data->recentAuditLogs as $log) {
        echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
        echo Html::div($log['createdAt'])->class('col-3 col-md-2 text-muted small');
        echo Html::div($log['action'])->class('col-9 col-md-4 text-break');
        echo Html::div($log['targetLabel'])->class('col-12 col-md-6 text-break small text-muted');
        echo Html::div()->close();
    }
    echo Html::div()->class('mt-3')->open();
    echo Html::a($translator->translate('voyti.view.audit_log.title'), $data->auditLogUrl)->class('btn btn-outline-secondary btn-sm');
    echo Html::div()->close();
}

echo Html::div()->close();
echo Html::div()->close();

echo Html::div()->close();
