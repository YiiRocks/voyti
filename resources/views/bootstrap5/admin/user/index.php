<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Admin\User\IndexViewData;
use YiiRocks\Voyti\ViewData\Admin\User\UserRow;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;
use Yiisoft\Yii\DataView\Pagination\OffsetPagination;

/**
 * @var WebView $this
 * @var IndexViewData $data
 * @var TranslatorInterface $translator
 * @var FlashViewData $flash
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.admin.title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['menu' => $data->menu]);
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_flash', ['flash' => $flash]);

echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
echo Html::H1($translator->translate('voyti.view.admin.title'));
echo Html::a($translator->translate('voyti.view.admin.create_user_link'), $data->createUserUrl)->class('btn', 'btn-primary');
echo Html::div()->close();

echo Html::form()
    ->action($data->filterActionUrl)
    ->method('get')
    ->open();

$tabindex = 0;

echo Html::div()->class('row mb-3 g-2')->open();
echo Html::div()->class('col')->open();
echo Html::input('text')->class('form-control')->name('username')->value($data->filters['username'])->addAttributes(['placeholder' => $translator->translate('voyti.view.username_header')])->attribute('tabindex', ++$tabindex);
echo Html::div()->close();

echo Html::div()->class('col')->open();
echo Html::input('text')->class('form-control')->name('email')->value($data->filters['email'])->addAttributes(['placeholder' => $translator->translate('voyti.view.email_header')])->attribute('tabindex', ++$tabindex);
echo Html::div()->close();

echo Html::div()->class('col')->open();
echo Html::select('status')
    ->class('form-select')
    ->prompt($translator->translate('voyti.view.status_header'))
    ->optionsData([
        'confirmed' => $translator->translate('voyti.view.status_active'),
        'unconfirmed' => $translator->translate('voyti.view.status_pending'),
        'blocked' => $translator->translate('voyti.view.status_blocked'),
    ])
    ->value($data->filters['status'])
    ->attribute('tabindex', ++$tabindex);
echo Html::div()->close();

echo Html::div()->class('col-auto')->open();
echo Field::buttonGroup()
    ->containerClass('btn-group')
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button'))->class('btn', 'btn-outline-secondary')->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.filter_button'))->class('btn', 'btn-outline-secondary')->attribute('tabindex', ++$tabindex),
    );
echo Html::div()->close();
echo Html::div()->close();

echo Html::form()->close();

if ($data->switchedBannerMessage !== null) {
    echo Html::div()->class('alert alert-warning d-flex justify-content-between align-items-center')->open();
    echo Html::span($data->switchedBannerMessage);
    echo Html::form()
        ->post($data->formSubmitUrl)
        ->csrf($csrf)
        ->open();
    echo Html::submitButton(
        $translator->translate('voyti.view.admin.restore_button'),
    )->class('btn', 'btn-warning', 'btn-sm');
    echo Html::form()->close();
    echo Html::div()->close();
}

echo Html::div()->class('d-none d-md-flex row fw-bold border-bottom pb-2 mb-2')->open();
echo Html::div($translator->translate('voyti.view.id_header'))->class('col-1');
echo Html::div($translator->translate('voyti.view.username_header'))->class('col-3');
echo Html::div($translator->translate('voyti.view.email_header'))->class('col-3');
echo Html::div($translator->translate('voyti.view.status_header'))->class('col-2');
echo Html::div($translator->translate('voyti.view.actions_header'))->class('col-3 text-end');
echo Html::div()->close();

foreach ($data->users as $user) {
    /** @var UserRow $user */
    echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
    echo Html::div($user->id)->class('col-1');
    echo Html::div($user->username)->class('col-3 text-break');
    echo Html::div($user->email)->class('col-3 text-break');
    echo Html::div()->class('col-2')->open();

    echo Html::span($user->statusLabel)->class('badge', $user->statusBadgeClass);

    echo Html::div()->close();

    echo Html::div()->class('col-3 text-end')->open();

    echo Html::a($translator->translate('voyti.view.info_link'), $user->showUrl)->class('btn', 'btn-sm', 'btn-outline-secondary', 'me-1');

    echo Html::div()->class('dropdown', 'd-inline-block')->open();
    echo Html::button($translator->translate('voyti.view.actions_header'))
        ->type('button')
        ->class('btn', 'btn-sm', 'btn-outline-secondary', 'dropdown-toggle')
        ->attribute('data-bs-toggle', 'dropdown')
        ->attribute('aria-expanded', 'false');
    echo Html::ul()->class('dropdown-menu', 'dropdown-menu-end')->open();

    echo Html::li(
        Html::a($translator->translate('voyti.view.update_link'), $user->updateUrl)->class('dropdown-item'),
    );

    echo Html::li(
        Html::a($translator->translate('voyti.view.update_profile_link'), $user->updateProfileUrl)->class('dropdown-item'),
    );

    echo Html::li(
        Html::a($translator->translate('voyti.view.admin.sessions_link'), $user->sessionsUrl)->class('dropdown-item'),
    );

    if ($user->showConfirmAction) {
        echo Html::li()->open();
        echo Html::form()
            ->post($user->confirmUrl)
            ->csrf($csrf)
            ->open();
        echo Html::submitButton($translator->translate('voyti.view.confirm_button'))->class('dropdown-item')->attribute('tabindex', 1);
        echo Html::form()->close();
        echo Html::li()->close();
    }

    if ($user->showForcePasswordChangeAction) {
        echo Html::li()->open();
        echo Html::form()
            ->post($user->forcePasswordChangeUrl)
            ->csrf($csrf)
            ->open();
        echo Html::submitButton($translator->translate('voyti.view.force_password_change_button'))->class('dropdown-item')->attribute('tabindex', 1);
        echo Html::form()->close();
        echo Html::li()->close();
    }

    echo Html::li()->open();
    echo Html::form()
        ->post($user->passwordResetUrl)
        ->csrf($csrf)
        ->open();
    echo Html::submitButton($translator->translate('voyti.view.reset_password_button'))->class('dropdown-item')->attribute('tabindex', 1);
    echo Html::form()->close();
    echo Html::li()->close();

    if ($user->showSwitchIdentityAction) {
        echo Html::li()->open();
        echo Html::form()
            ->post($user->switchIdentityUrl)
            ->csrf($csrf)
            ->open();
        echo Html::submitButton($translator->translate('voyti.view.admin.switch_button'))
            ->class('dropdown-item')
            ->attribute('tabindex', 1)
            ->disabled($user->switchIdentityDisabled);
        echo Html::form()->close();
        echo Html::li()->close();
    }

    echo Html::li(Html::hr()->class('dropdown-divider'));

    echo Html::li()->open();
    echo Html::form()
        ->post($user->blockToggleUrl)
        ->csrf($csrf)
        ->open();
    echo Html::submitButton($user->blockToggleLabel)->class('dropdown-item', 'text-warning')->attribute('tabindex', 1);
    echo Html::form()->close();
    echo Html::li()->close();

    echo Html::li()->open();
    echo Html::form()
        ->post($user->deleteUrl)
        ->csrf($csrf)
        ->open();
    echo Html::submitButton($translator->translate('voyti.view.delete_button'))->class('dropdown-item', 'text-danger')->attribute('tabindex', 1);
    echo Html::form()->close();
    echo Html::li()->close();

    echo Html::ul()->close();
    echo Html::div()->close();
    echo Html::div()->close();
    echo Html::div()->close();
}

echo OffsetPagination::create(
    $data->paginator,
    $data->pageUrlPattern,
    $data->firstPageUrl,
)
    ->containerAttributes(['aria-label' => $translator->translate('voyti.view.pagination_navigation')])
    ->listTag('ul')
    ->listAttributes(['class' => 'pagination justify-content-center'])
    ->itemTag('li')
    ->itemAttributes(['class' => 'page-item'])
    ->currentItemClass('active')
    ->linkAttributes(['class' => 'page-link'])
    ->labelFirst(null)
    ->labelLast(null)
    ->labelPrevious($translator->translate('voyti.view.previous'))
    ->labelNext($translator->translate('voyti.view.next'))
    ->render();
echo Html::div()->close();
