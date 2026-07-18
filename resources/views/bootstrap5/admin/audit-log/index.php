<?php

declare(strict_types=1);

use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\DataView\Pagination\OffsetPagination;
use Yiisoft\Yii\DataView\Pagination\PaginationContext;

/**
 * @var WebView $this
 * @var list<array{createdAt: string, actorUserId: string, action: string, targetLabel: string, context: string}> $logs
 * @var array<string, string> $filters
 * @var OffsetPaginator $paginator
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var FlashInterface $flash
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.audit_log.title', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['url' => $url, 'translator' => $translator]);
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_flash', ['flash' => $flash]);

echo Html::H1($translator->translate('voyti.view.audit_log.title', category: 'voyti'));

echo Html::form()
    ->action($url->generate('voyti/admin-audit-log'))
    ->method('get')
    ->open();

$tabindex = 0;

echo Html::div()->class('row mb-3 g-2')->open();
echo Html::div()->class('col')->open();
echo Html::input('text')->class('form-control')->name('actorUserId')->value($filters['actor_user_id'] ?? '')->addAttributes(['placeholder' => $translator->translate('voyti.view.audit_log.actor_header', category: 'voyti')])->attribute('tabindex', ++$tabindex);
echo Html::div()->close();

echo Html::div()->class('col')->open();
echo Html::input('text')->class('form-control')->name('targetUserId')->value($filters['target_user_id'] ?? '')->addAttributes(['placeholder' => $translator->translate('voyti.view.audit_log.target_header', category: 'voyti')])->attribute('tabindex', ++$tabindex);
echo Html::div()->close();

echo Html::div()->class('col')->open();
echo Html::input('text')->class('form-control')->name('action')->value($filters['action'] ?? '')->addAttributes(['placeholder' => $translator->translate('voyti.view.audit_log.action_header', category: 'voyti')])->attribute('tabindex', ++$tabindex);
echo Html::div()->close();

echo Html::div()->class('col-auto')->open();
echo Field::buttonGroup()
    ->containerClass('btn-group')
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->class('btn', 'btn-outline-secondary')->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.filter_button', category: 'voyti'))->class('btn', 'btn-outline-secondary')->attribute('tabindex', ++$tabindex),
    );
echo Html::div()->close();
echo Html::div()->close();

echo Html::form()->close();

echo Html::div()->class('d-none d-md-flex row fw-bold border-bottom pb-2 mb-2')->open();
echo Html::div($translator->translate('voyti.view.audit_log.created_header', category: 'voyti'))->class('col-2');
echo Html::div($translator->translate('voyti.view.audit_log.actor_header', category: 'voyti'))->class('col-2');
echo Html::div($translator->translate('voyti.view.audit_log.action_header', category: 'voyti'))->class('col-2');
echo Html::div($translator->translate('voyti.view.audit_log.target_header', category: 'voyti'))->class('col-2');
echo Html::div($translator->translate('voyti.view.audit_log.context_header', category: 'voyti'))->class('col-4');
echo Html::div()->close();

foreach ($logs as $log) {
    echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
    echo Html::div($log['createdAt'])->class('col-2');
    echo Html::div($log['actorUserId'])->class('col-2');
    echo Html::div($log['action'])->class('col-2 text-break');
    echo Html::div($log['targetLabel'])->class('col-2 text-break');
    echo Html::div($log['context'])->class('col-4 text-break small');
    echo Html::div()->close();
}

$pageQuery = [
    'actorUserId' => $filters['actor_user_id'] ?? '',
    'targetUserId' => $filters['target_user_id'] ?? '',
    'action' => $filters['action'] ?? '',
];

echo OffsetPagination::create(
    $paginator,
    $url->generate('voyti/admin-audit-log', [], [...$pageQuery, 'page' => PaginationContext::URL_PLACEHOLDER]),
    $url->generate('voyti/admin-audit-log', [], [...$pageQuery, 'page' => '1']),
)
    ->containerAttributes(['aria-label' => $translator->translate('voyti.view.pagination_navigation', category: 'voyti')])
    ->listTag('ul')
    ->listAttributes(['class' => 'pagination justify-content-center'])
    ->itemTag('li')
    ->itemAttributes(['class' => 'page-item'])
    ->currentItemClass('active')
    ->linkAttributes(['class' => 'page-link'])
    ->labelFirst(null)
    ->labelLast(null)
    ->labelPrevious($translator->translate('voyti.view.previous', category: 'voyti'))
    ->labelNext($translator->translate('voyti.view.next', category: 'voyti'))
    ->render();
echo Html::div()->close();
