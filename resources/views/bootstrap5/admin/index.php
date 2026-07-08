<?php

declare(strict_types=1);

use YiiRocks\Voyti\Entity\User;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var array $users
 * @var array<string, string> $filters
 * @var int $totalPages
 * @var int $currentPage
 * @var ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var FlashInterface $flash
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.admin.title', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_admin-menu', ['url' => $url, 'translator' => $translator]);
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash', ['flash' => $flash]);

echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
echo Html::H1($translator->translate('voyti.view.admin.title', category: 'voyti'));
echo Html::a($translator->translate('voyti.view.admin.create_user_link', category: 'voyti'), $url->generate('voyti/admin-create'))->class('btn', 'btn-primary');
echo Html::div()->close();

echo Html::form()
    ->action($url->generate('voyti/admin'))
    ->method('get')
    ->open();

$tabindex = 0;

echo Html::div()->class('row mb-3 g-2')->open();
echo Html::div()->class('col')->open();
echo Html::input('text')->class('form-control')->name('username')->value($filters['username'] ?? '')->addAttributes(['placeholder' => $translator->translate('voyti.view.username_header', category: 'voyti')])->attribute('tabindex', ++$tabindex);
echo Html::div()->close();

echo Html::div()->class('col')->open();
echo Html::input('text')->class('form-control')->name('email')->value($filters['email'] ?? '')->addAttributes(['placeholder' => $translator->translate('voyti.view.email_header', category: 'voyti')])->attribute('tabindex', ++$tabindex);
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
    ->value($filters['status'] ?? '')
    ->attribute('tabindex', ++$tabindex);
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
echo Html::div($translator->translate('voyti.view.id_header', category: 'voyti'))->class('col-1');
echo Html::div($translator->translate('voyti.view.username_header', category: 'voyti'))->class('col-3');
echo Html::div($translator->translate('voyti.view.email_header', category: 'voyti'))->class('col-3');
echo Html::div($translator->translate('voyti.view.status_header', category: 'voyti'))->class('col-2');
echo Html::div($translator->translate('voyti.view.actions_header', category: 'voyti'))->class('col-3 text-end');
echo Html::div()->close();

/** @var User $user */
foreach ($users as $user) {
    echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
    echo Html::div($user->getId())->class('col-1');
    echo Html::div($user->getUsername())->class('col-3 text-break');
    echo Html::div($user->getEmail())->class('col-3 text-break');
    echo Html::div()->class('col-2')->open();

    if ($user->isBlocked()) {
        echo Html::span($translator->translate('voyti.view.status_blocked', category: 'voyti'))->class('badge bg-danger');
    } elseif ($user->isConfirmed()) {
        echo Html::span($translator->translate('voyti.view.status_active', category: 'voyti'))->class('badge bg-success');
    } else {
        echo Html::span($translator->translate('voyti.view.status_pending', category: 'voyti'))->class('badge bg-warning text-dark');
    }

    echo Html::div()->close();

    echo Html::div()->class('col-3 text-end')->open();

    echo Html::a($translator->translate('voyti.view.info_link', category: 'voyti'), $url->generate('voyti/admin-info', ['id' => $user->getId()]))->class('btn', 'btn-sm', 'btn-outline-secondary', 'me-1');

    echo Html::div()->class('dropdown', 'd-inline-block')->open();
    echo Html::button($translator->translate('voyti.view.actions_header', category: 'voyti'))
        ->type('button')
        ->class('btn', 'btn-sm', 'btn-outline-secondary', 'dropdown-toggle')
        ->attribute('data-bs-toggle', 'dropdown')
        ->attribute('aria-expanded', 'false');
    echo Html::ul()->class('dropdown-menu', 'dropdown-menu-end')->open();

    echo Html::li(
        Html::a($translator->translate('voyti.view.update_link', category: 'voyti'), $url->generate('voyti/admin-update', ['id' => $user->getId()]))->class('dropdown-item'),
    );

    if (!$user->isConfirmed()) {
        echo Html::li()->open();
        echo Html::form()
            ->post($url->generate('voyti/admin-confirm', ['id' => $user->getId()]))
            ->csrf($csrf)
            ->open();
        echo Html::submitButton($translator->translate('voyti.view.confirm_button', category: 'voyti'))->class('dropdown-item')->attribute('tabindex', 1);
        echo Html::form()->close();
        echo Html::li()->close();
    }

    if ($config->enablePasswordExpiration) {
        echo Html::li()->open();
        echo Html::form()
            ->post($url->generate('voyti/admin-force-password', ['id' => $user->getId()]))
            ->csrf($csrf)
            ->open();
        echo Html::submitButton($translator->translate('voyti.view.force_password_change_button', category: 'voyti'))->class('dropdown-item')->attribute('tabindex', 1);
        echo Html::form()->close();
        echo Html::li()->close();
    }

    echo Html::li()->open();
    echo Html::form()
        ->post($url->generate('voyti/admin-password-reset', ['id' => $user->getId()]))
        ->csrf($csrf)
        ->open();
    echo Html::submitButton($translator->translate('voyti.view.reset_password_button', category: 'voyti'))->class('dropdown-item')->attribute('tabindex', 1);
    echo Html::form()->close();
    echo Html::li()->close();

    echo Html::li(Html::hr()->class('dropdown-divider'));

    echo Html::li()->open();
    echo Html::form()
        ->post($url->generate('voyti/admin-block', ['id' => $user->getId()]))
        ->csrf($csrf)
        ->open();
    echo Html::submitButton(
        $translator->translate($user->isBlocked() ? 'voyti.view.unblock_button' : 'voyti.view.block_button', category: 'voyti')
    )->class('dropdown-item', 'text-warning')->attribute('tabindex', 1);
    echo Html::form()->close();
    echo Html::li()->close();

    echo Html::li()->open();
    echo Html::form()
        ->post($url->generate('voyti/admin-delete', ['id' => $user->getId()]))
        ->csrf($csrf)
        ->open();
    echo Html::submitButton($translator->translate('voyti.view.delete_button', category: 'voyti'))->class('dropdown-item', 'text-danger')->attribute('tabindex', 1);
    echo Html::form()->close();
    echo Html::li()->close();

    echo Html::ul()->close();
    echo Html::div()->close();
    echo Html::div()->close();
    echo Html::div()->close();
}

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
        ->content(Html::ul()->class('pagination', 'justify-content-center')->items(...$items))
        ->encode(false);
}
echo Html::div()->close();
