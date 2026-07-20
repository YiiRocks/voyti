<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Admin\Rbac\IndexViewData;
use YiiRocks\Voyti\ViewData\Admin\Rbac\RbacItemRow;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var IndexViewData $data
 * @var TranslatorInterface $translator
 * @var FlashViewData $flash
 * @var string $csrf
 */

$descriptionColClass = 'col-4';
$actionsColClass = 'col-3 text-end';

/** @psalm-suppress InvalidScope */
$this->setTitle($data->title);

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['menu' => $data->menu]);
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_flash', ['flash' => $flash]);

echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
echo Html::H1($data->title);
echo Html::a($data->createLinkLabel, $data->createUrl)->class('btn', 'btn-primary');
echo Html::div()->close();

echo Html::form()
    ->get($data->filterUrl)
    ->class('mb-3')
    ->open();

$tabindex = 0;

echo Html::div()->class('row g-2')->open();
echo Html::div()->class('col')->open();
echo Html::textInput()->class('form-control')->name('name')->value($data->filterName)->addAttributes(['placeholder' => $translator->translate('voyti.view.name_label')])->attribute('tabindex', ++$tabindex);
echo Html::div()->close();

echo Html::div()->class('col')->open();
echo Html::textInput()->class('form-control')->name('description')->value($data->filterDescription)->addAttributes(['placeholder' => $translator->translate('voyti.view.description_label')])->attribute('tabindex', ++$tabindex);
echo Html::div()->close();

echo Html::div()->class('col-auto')->open();
echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button'))->class('btn', 'btn-outline-secondary')->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.filter_button'))->class('btn', 'btn-outline-secondary')->attribute('tabindex', ++$tabindex),
    );
echo Html::div()->close();
echo Html::div()->close();

echo Html::form()->close();

echo Html::div()->class('d-none d-md-flex row fw-bold border-bottom pb-2 mb-2')->open();
echo Html::div($translator->translate('voyti.view.name_header'))->class('col-3');
echo Html::div($translator->translate('voyti.view.description_header'))->class($descriptionColClass);
echo Html::div($translator->translate('voyti.view.children_header'))->class('col-2');
echo Html::div($translator->translate('voyti.view.actions_header'))->class($actionsColClass);
echo Html::div()->close();

foreach ($data->items as $item) {
    /** @var RbacItemRow $item */
    echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
    echo Html::div($item->name)->class('col-3 text-break');
    echo Html::div($item->description)->class($descriptionColClass . ' text-break');
    echo Html::div($item->childrenDisplay)->class('col-2 text-break');
    echo Html::div()->class($actionsColClass)->open();
    echo Html::a($translator->translate('voyti.view.update_link'), $item->updateUrl)->class('btn', 'btn-sm', 'btn-outline-secondary', 'me-1');

    echo Html::form()
        ->post($item->formSubmitUrl)
        ->csrf($csrf)
        ->class('d-inline')
        ->open();
    echo Html::submitButton($translator->translate('voyti.view.delete_button'))->class('btn', 'btn-sm', 'btn-outline-danger')->attribute('tabindex', 1);
    echo Html::form()->close();
    echo Html::div()->close();
    echo Html::div()->close();
}
echo Html::div()->close();
