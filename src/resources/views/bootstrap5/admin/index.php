<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var array $users
 * @var array $filters
 * @var int $totalPages
 * @var int $currentPage
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

$this->setTitle($translator->translate('voyti.view.admin.title', category: 'voyti'));

echo Html::div()->class('voyti-admin-index')->open();
echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
echo Html::H1($translator->translate('voyti.view.admin.title', category: 'voyti'));
echo Html::a($translator->translate('voyti.view.admin.create_user_link', category: 'voyti'), $url->generate('voyti/admin-create'))->class('btn', 'btn-primary');
echo Html::div()->close();

echo Html::form()
    ->action($url->generate('voyti/admin'))
    ->method('get')
    ->open();

echo Html::div()->class('row g-2')->open();
echo Html::div()->class('col')->open();
echo Html::input('text')->class('form-control')->name('username')->value($filters['username'] ?? '')->addAttributes(['placeholder' => $translator->translate('voyti.view.username_header', category: 'voyti')]);
echo Html::div()->close();

echo Html::div()->class('col')->open();
echo Html::input('text')->class('form-control')->name('email')->value($filters['email'] ?? '')->addAttributes(['placeholder' => $translator->translate('voyti.view.email_header', category: 'voyti')]);
echo Html::div()->close();

echo Html::div()->class('col')->open();
echo Html::select('status')
    ->class('form-select')
    ->prompt($translator->translate('voyti.view.status_header', category: 'voyti'))
    ->optionsData([
        'confirmed' => $translator->translate('voyti.view.status_active', category: 'voyti'),
        'unconfirmed' => $translator->translate('voyti.view.status_pending', category: 'voyti'),
        'blocked' => $translator->translate('voyti.view.status_blocked', category: 'voyti'),
    ])
    ->value($filters['status'] ?? '');
echo Html::div()->close();

echo Html::div()->class('col-auto')->open();
echo Field::buttonGroup()
    ->buttons(
        Html::submitButton($translator->translate('voyti.view.filter_button', category: 'voyti'))->class('btn', 'btn-outline-secondary')
    );
echo Html::div()->close();
echo Html::div()->close();

echo Html::form()->close();

echo Html::div()->class('table-responsive')->open();
echo Html::table()->class('table table-striped table-hover')->open();

echo Html::tag('thead')->class('table-light')->open();
echo Html::tag('tr')->open();
echo Html::tag('th', $translator->translate('voyti.view.id_header', category: 'voyti'))->addAttributes(['scope' => 'col']);
echo Html::tag('th', $translator->translate('voyti.view.username_header', category: 'voyti'))->addAttributes(['scope' => 'col']);
echo Html::tag('th', $translator->translate('voyti.view.email_header', category: 'voyti'))->addAttributes(['scope' => 'col']);
echo Html::tag('th', $translator->translate('voyti.view.status_header', category: 'voyti'))->addAttributes(['scope' => 'col']);
echo Html::tag('th', $translator->translate('voyti.view.actions_header', category: 'voyti'))->class('text-end')->addAttributes(['scope' => 'col']);
echo Html::tag('tr')->close();
echo Html::tag('thead')->close();

echo Html::tag('tbody')->open();

foreach ($users as $user) {
    echo Html::tag('tr')->open();
    echo Html::tag('td', $user->getId());
    echo Html::tag('td', $user->getUsername());
    echo Html::tag('td', $user->getEmail());
    echo Html::tag('td')->open();

    if ($user->isBlocked()) {
        echo Html::tag('span', $translator->translate('voyti.view.status_blocked', category: 'voyti'))->class('badge bg-danger');
    } elseif ($user->isConfirmed()) {
        echo Html::tag('span', $translator->translate('voyti.view.status_active', category: 'voyti'))->class('badge bg-success');
    } else {
        echo Html::tag('span', $translator->translate('voyti.view.status_pending', category: 'voyti'))->class('badge bg-warning text-dark');
    }

    echo Html::tag('td')->close();

    echo Html::tag('td')->class('text-end')->open();
    echo Html::a($translator->translate('voyti.view.info_link', category: 'voyti'), $url->generate('voyti/admin-info', ['id' => $user->getId()]))->class('btn', 'btn-sm', 'btn-outline-secondary');
    echo ' ';
    echo Html::a($translator->translate('voyti.view.update_link', category: 'voyti'), $url->generate('voyti/admin-update', ['id' => $user->getId()]))->class('btn', 'btn-sm', 'btn-outline-secondary');
    echo ' ';

    echo Html::form()
        ->post($url->generate('voyti/admin-delete', ['id' => $user->getId()]))
        ->csrf($csrf)
        ->class('d-inline')
        ->open();

    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.delete_button', category: 'voyti'))->class('btn', 'btn-sm', 'btn-outline-danger')
        );

    echo Html::form()->close();
    echo Html::tag('td')->close();
    echo Html::tag('tr')->close();
}

echo Html::tag('tbody')->close();
echo Html::table()->close();
echo Html::div()->close();

if ($totalPages > 1) {
    $pageQuery = [
        'username' => $filters['username'] ?? '',
        'email' => $filters['email'] ?? '',
        'status' => $filters['status'] ?? '',
    ];

    $items = [];

    if ($currentPage > 1) {
        $items[] = Html::li(
            Html::a(
                $translator->translate('voyti.view.previous', category: 'voyti'),
                $url->generate('voyti/admin', [], ['page' => $currentPage - 1, ...$pageQuery]),
            )->class('page-link'),
        )->class('page-item');
    }

    for ($i = 1; $i <= $totalPages; $i++) {
        $items[] = Html::li(
            Html::a((string) $i, $url->generate('voyti/admin', [], ['page' => $i, ...$pageQuery]))->class('page-link'),
        )->class('page-item' . ($i === $currentPage ? ' active' : ''));
    }

    if ($currentPage < $totalPages) {
        $items[] = Html::li(
            Html::a(
                $translator->translate('voyti.view.next', category: 'voyti'),
                $url->generate('voyti/admin', [], ['page' => $currentPage + 1, ...$pageQuery]),
            )->class('page-link'),
        )->class('page-item');
    }

    echo Html::nav()
        ->attribute('aria-label', $translator->translate('voyti.view.pagination_navigation', category: 'voyti'))
        ->content("\n" . Html::ul()->class('pagination', 'justify-content-center')->items(...$items) . "\n");
}
echo Html::div()->close();
