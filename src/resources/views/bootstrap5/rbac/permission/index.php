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
echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
echo Html::H1($translator->translate('voyti.view.permission.title', category: 'voyti'));
echo Html::a($translator->translate('voyti.view.permission.create_link', category: 'voyti'), $url->generate('voyti/permissions-create'))->class('btn', 'btn-primary');
echo Html::div()->close();

echo Html::form()
    ->action($url->generate('voyti/permissions'))
    ->method('get')
    ->class('mb-3')
    ->open();

echo Html::div()->class('row g-2')->open();
echo Html::div()->class('col')->open();
echo Html::input('text')->class('form-control')->name('name')->value($filterName)->addAttributes(['placeholder' => $translator->translate('voyti.view.name_label', category: 'voyti')]);
echo Html::div()->close();

echo Html::div()->class('col')->open();
echo Html::input('text')->class('form-control')->name('description')->value($filterDescription)->addAttributes(['placeholder' => $translator->translate('voyti.view.description_label', category: 'voyti')]);
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
echo Html::tag('th', $translator->translate('voyti.view.name_header', category: 'voyti'))->addAttributes(['scope' => 'col']);
echo Html::tag('th', $translator->translate('voyti.view.description_header', category: 'voyti'))->addAttributes(['scope' => 'col']);
echo Html::tag('th', $translator->translate('voyti.view.actions_header', category: 'voyti'))->class('text-end')->addAttributes(['scope' => 'col']);
echo Html::tag('tr')->close();
echo Html::tag('thead')->close();

echo Html::tag('tbody')->open();

/** @var Yiisoft\Rbac\Item $perm */
foreach ($items as $perm) {
    echo Html::tag('tr')->open();
    echo Html::tag('td', $perm->getName());
    echo Html::tag('td', $perm->getDescription());
    echo Html::tag('td')->class('text-end')->open();
    echo Html::a($translator->translate('voyti.view.update_link', category: 'voyti'), $url->generate('voyti/permissions-update', ['name' => $perm->getName()]))->class('btn', 'btn-sm', 'btn-outline-secondary');
    echo ' ';

    echo Html::form()
        ->post($url->generate('voyti/permissions-delete', ['name' => $perm->getName()]))
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
echo Html::div()->close();
