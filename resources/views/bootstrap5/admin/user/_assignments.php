<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\User;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Rbac\Item;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var User $user
 * @var string[] $assignments Array of assigned item names
 * @var array<array-key, Item> $available Array of unassigned items (name => Item)
 * @var TranslatorInterface $translator
 * @var UrlGeneratorInterface $url
 * @var string $csrf
 */

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['url' => $url, 'translator' => $translator]);

Html::H3()->class('mb-3')->open();
echo $translator->translate('voyti.view.assignments.title', category: 'voyti');
echo Html::H3()->close();

echo Html::form()
    ->post($url->generate('voyti/admin-users-assignments', ['id' => $user->getId()]))
    ->csrf($csrf)
    ->open();

$tabindex = 0;

echo Html::div()->class('row g-3')->open();
echo Html::div()->class('col-md-6')->open();
echo Html::div($translator->translate('voyti.view.assignments.assigned', category: 'voyti'))->class('fw-bold mb-2');
foreach ($assignments as $itemName) {
    echo Html::div()->class('form-check')->open();
    echo Html::input('checkbox')->class('form-check-input')->name('items[]')->value($itemName)->addAttributes(['checked' => true])->attribute('tabindex', ++$tabindex);
    echo Html::label($itemName)->class('form-check-label');
    echo Html::div()->close();
}
echo Html::div()->close();
echo Html::div()->class('col-md-6')->open();
echo Html::div($translator->translate('voyti.view.assignments.available', category: 'voyti'))->class('fw-bold mb-2');
foreach ($available as $name => $item) {
    echo Html::div()->class('form-check')->open();
    echo Html::input('checkbox')->class('form-check-input')->name('items[]')->value($name)->attribute('tabindex', ++$tabindex);
    echo Html::label($name)->class('form-check-label');
    echo Html::div()->close();
}
echo Html::div()->close();
echo Html::div()->close();

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.assignments.update', category: 'voyti'))->class('btn', 'btn-primary')->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
