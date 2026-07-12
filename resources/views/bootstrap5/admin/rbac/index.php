<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Rbac\Item;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var string $itemType 'role' or 'permission'
 * @var array<string, Item> $items
 * @var array<string, list<string>> $itemChildren
 * @var string $filterName
 * @var string $filterDescription
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var FlashInterface $flash
 * @var string $csrf
 */

$isRole = $itemType === 'role';
$routePrefix = 'admin-rbac-' . $itemType . 's';
$descriptionColClass = $isRole ? 'col-4' : 'col-5';
$actionsColClass = $isRole ? 'col-3 text-end' : 'col-4 text-end';

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.' . $itemType . '.title', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['url' => $url, 'translator' => $translator]);
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_flash', ['flash' => $flash]);

echo Html::div()->class('d-flex justify-content-between align-items-center mb-3')->open();
echo Html::H1($translator->translate('voyti.view.' . $itemType . '.title', category: 'voyti'));
echo Html::a($translator->translate('voyti.view.' . $itemType . '.create_link', category: 'voyti'), $url->generate('voyti/' . $routePrefix . '-create'))->class('btn', 'btn-primary');
echo Html::div()->close();

echo Html::form()
    ->get($url->generate('voyti/' . $routePrefix))
    ->class('mb-3')
    ->open();

$tabindex = 0;

echo Html::div()->class('row g-2')->open();
echo Html::div()->class('col')->open();
echo Html::textInput()->class('form-control')->name('name')->value($filterName)->addAttributes(['placeholder' => $translator->translate('voyti.view.name_label', category: 'voyti')])->attribute('tabindex', ++$tabindex);
echo Html::div()->close();

echo Html::div()->class('col')->open();
echo Html::textInput()->class('form-control')->name('description')->value($filterDescription)->addAttributes(['placeholder' => $translator->translate('voyti.view.description_label', category: 'voyti')])->attribute('tabindex', ++$tabindex);
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
echo Html::div($translator->translate('voyti.view.description_header', category: 'voyti'))->class($descriptionColClass);
if ($isRole) {
    echo Html::div($translator->translate('voyti.view.children_header', category: 'voyti'))->class('col-2');
}
echo Html::div($translator->translate('voyti.view.actions_header', category: 'voyti'))->class($actionsColClass);
echo Html::div()->close();

foreach ($items as $item) {
    echo Html::div()->class('row py-2 border-bottom align-items-center')->open();
    echo Html::div($item->getName())->class('col-3 text-break');
    echo Html::div($item->getDescription())->class($descriptionColClass . ' text-break');
    if ($isRole) {
        echo Html::div(implode(', ', $itemChildren[$item->getName()] ?? []))->class('col-2 text-break');
    }
    echo Html::div()->class($actionsColClass)->open();
    echo Html::a($translator->translate('voyti.view.update_link', category: 'voyti'), $url->generate('voyti/' . $routePrefix . '-update', ['name' => $item->getName()]))->class('btn', 'btn-sm', 'btn-outline-secondary', 'me-1');

    echo Html::form()
        ->post($url->generate('voyti/' . $routePrefix . '-delete', ['name' => $item->getName()]))
        ->csrf($csrf)
        ->class('d-inline')
        ->open();
    echo Html::submitButton($translator->translate('voyti.view.delete_button', category: 'voyti'))->class('btn', 'btn-sm', 'btn-outline-danger')->attribute('tabindex', 1);
    echo Html::form()->close();
    echo Html::div()->close();
    echo Html::div()->close();
}
echo Html::div()->close();
