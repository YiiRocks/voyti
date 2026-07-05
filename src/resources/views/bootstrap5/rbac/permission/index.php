<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var array $items Array of Permission objects
 * @var string $filterName
 * @var string $filterDescription
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.permission.title', category: 'voyti'));

echo Html::div()->open();
include dirname(__DIR__, 2) . '/shared/_admin-menu.php';

echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
echo Html::H1($translator->translate('voyti.view.permission.title', category: 'voyti'));
echo Html::a($translator->translate('voyti.view.permission.create_link', category: 'voyti'), $url->generate('voyti/permissions-create'))->class('btn', 'btn-primary');
echo Html::div()->close();

echo Html::form()
    ->action($url->generate('voyti/permissions'))
    ->method('get')
    ->class('mb-3')
    ->open();

$tabindex = 0;

echo Html::div()->class('row g-2')->open();
echo Html::div()->class('col')->open();
echo Html::input('text')->class('form-control')->name('name')->value($filterName)->addAttributes(['placeholder' => $translator->translate('voyti.view.name_label', category: 'voyti')])->attribute('tabindex', ++$tabindex);
echo Html::div()->close();

echo Html::div()->class('col')->open();
echo Html::input('text')->class('form-control')->name('description')->value($filterDescription)->addAttributes(['placeholder' => $translator->translate('voyti.view.description_label', category: 'voyti')])->attribute('tabindex', ++$tabindex);
echo Html::div()->close();

echo Html::div()->class('col-auto')->open();
echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->class('btn', 'btn-outline-secondary')->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.filter_button', category: 'voyti'))->class('btn', 'btn-outline-secondary')->attribute('tabindex', ++$tabindex),
    );
echo Html::div()->close();
echo Html::div()->close();

echo Html::form()->close();

echo Html::div()->class('d-none d-md-flex row fw-bold border-bottom pb-2 mb-2')->open();
echo Html::div($translator->translate('voyti.view.name_header', category: 'voyti'))->class('col-3');
echo Html::div($translator->translate('voyti.view.description_header', category: 'voyti'))->class('col-5');
echo Html::div($translator->translate('voyti.view.actions_header', category: 'voyti'))->class('col-4 text-end');
echo Html::div()->close();

/** @var Yiisoft\Rbac\Item $perm */
foreach ($items as $perm) {
    echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
    echo Html::div($perm->getName())->class('col-3 text-break');
    echo Html::div($perm->getDescription())->class('col-5 text-break');
    echo Html::div()->class('col-4 text-end')->open();
    echo Html::a($translator->translate('voyti.view.update_link', category: 'voyti'), $url->generate('voyti/permissions-update', ['name' => $perm->getName()]))->class('btn', 'btn-sm', 'btn-outline-secondary', 'me-1');

    echo Html::form()
        ->post($url->generate('voyti/permissions-delete', ['name' => $perm->getName()]))
        ->csrf($csrf)
        ->class('d-inline')
        ->open();
    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.delete_button', category: 'voyti'))->class('btn', 'btn-sm', 'btn-outline-danger')->attribute('tabindex', 1),
        );
    echo Html::form()->close();
    echo Html::div()->close();
    echo Html::div()->close();
}
echo Html::div()->close();
