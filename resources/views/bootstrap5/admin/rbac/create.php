<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Rbac\AuthItemForm;
use YiiRocks\Voyti\ViewData\Admin\Rbac\CreateViewData;
use YiiRocks\Voyti\ViewData\Shared\AssignableItemRow;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var AuthItemForm $form
 * @var CreateViewData $data
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($data->title);

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['menu' => $data->menu]);

echo Html::H1($data->title);

echo Html::form()
    ->post($data->formSubmitUrl)
    ->csrf($csrf)
    ->open();

if (!empty($data->errors)) {
    echo Html::div()->class('alert alert-danger')->open();
    foreach ($data->errors as $fieldErrors) {
        foreach ($fieldErrors as $error) {
            echo Html::div($error);
        }
    }
    echo Html::div()->close();
}

$tabindex = 0;

echo Field::text($form, 'name')->tabIndex(++$tabindex);

echo Field::text($form, 'description')->tabIndex(++$tabindex);

echo Field::text($form, 'rule')->tabIndex(++$tabindex);

echo Html::h3($translator->translate('voyti.view.children_header'))->class('mb-3');
echo Html::div()->class('mb-3')->open();
foreach ($data->children as $child) {
    /** @var AssignableItemRow $child */
    echo Html::div()->class('form-check')->open();
    echo Html::input('checkbox')
        ->class('form-check-input')
        ->name($form->getFormName() . '[children][]')
        ->value($child->name)
        ->addAttributes($child->checked ? ['checked' => true] : [])
        ->attribute('tabindex', ++$tabindex);
    echo Html::label($child->name)->class('form-check-label');
    echo Html::div()->close();
}
echo Html::div()->close();

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.create_button'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
