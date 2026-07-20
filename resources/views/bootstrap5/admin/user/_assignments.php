<?php

declare(strict_types=1);

use YiiRocks\Voyti\ViewData\Admin\User\AssignmentsViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var AssignmentsViewData $data
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.assignments.title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['menu' => $data->menu]);

echo Html::H3()->class('mb-3')->open();
echo $translator->translate('voyti.view.assignments.title');
echo Html::H3()->close();

echo Html::form()
    ->post($data->formSubmitUrl)
    ->csrf($csrf)
    ->open();

$tabindex = 0;

echo Html::div()->class('row g-3')->open();
echo Html::div()->class('col-md-6')->open();
echo Html::div($translator->translate('voyti.view.assignments.assigned'))->class('fw-bold mb-2');
foreach ($data->assignedItemNames as $itemName) {
    echo Html::div()->class('form-check')->open();
    echo Html::input('checkbox')->class('form-check-input')->name('items[]')->value($itemName)->addAttributes(['checked' => true])->attribute('tabindex', ++$tabindex);
    echo Html::label($itemName)->class('form-check-label');
    echo Html::div()->close();
}
echo Html::div()->close();
echo Html::div()->class('col-md-6')->open();
echo Html::div($translator->translate('voyti.view.assignments.available'))->class('fw-bold mb-2');
foreach ($data->availableItemNames as $itemName) {
    echo Html::div()->class('form-check')->open();
    echo Html::input('checkbox')->class('form-check-input')->name('items[]')->value($itemName)->attribute('tabindex', ++$tabindex);
    echo Html::label($itemName)->class('form-check-label');
    echo Html::div()->close();
}
echo Html::div()->close();
echo Html::div()->close();

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.assignments.update'))->class('btn', 'btn-primary')->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
