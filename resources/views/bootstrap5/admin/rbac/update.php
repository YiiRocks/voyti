<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Rbac\AuthItemForm;
use YiiRocks\Voyti\Model\User;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Rbac\Item;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var string $itemType 'role' or 'permission'
 * @var AuthItemForm $model
 * @var array<string, Item> $availableChildren
 * @var array<string, list<string>> $errors
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 * @var list<User> $users
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.' . $itemType . '.update_title', ['name' => $model->itemName], category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../../shared/_admin-menu', ['url' => $url, 'translator' => $translator]);

echo Html::H1($translator->translate('voyti.view.' . $itemType . '.update_title', ['name' => $model->itemName], category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/admin-rbac-' . $itemType . 's-update', ['name' => $model->itemName]))
    ->csrf($csrf)
    ->open();

if (!empty($errors)) {
    echo Html::div()->class('alert alert-danger')->open();
    foreach ($errors as $field => $fieldErrors) {
        /** @var string $error */
        foreach ($fieldErrors as $error) {
            echo Html::div($error);
        }
    }
    echo Html::div()->close();
}

$tabindex = 0;

echo Field::text($model, 'name')->tabIndex(++$tabindex);

echo Field::text($model, 'description')->tabIndex(++$tabindex);

echo Field::text($model, 'rule')->tabIndex(++$tabindex);

echo Html::h3($translator->translate('voyti.view.children_header', category: 'voyti'))->class('mb-3');
echo Html::div()->class('mb-3')->open();
/** @var list<string> $selectedChildren */
$selectedChildren = $model->children;
foreach ($availableChildren as $child) {
    $isChecked = in_array($child->getName(), $selectedChildren, true);
    echo Html::div()->class('form-check')->open();
    echo Html::input('checkbox')
        ->class('form-check-input')
        ->name($model->getFormName() . '[children][]')
        ->value($child->getName())
        ->addAttributes($isChecked ? ['checked' => true] : [])
        ->attribute('tabindex', ++$tabindex);
    echo Html::label($child->getName())->class('form-check-label');
    echo Html::div()->close();
}
echo Html::div()->close();

echo Html::h3($translator->translate('voyti.view.assignments.title', category: 'voyti'))->class('mb-3');

echo Html::div()->class('mb-3')->open();
echo Html::div($translator->translate('voyti.view.assignments.assigned', category: 'voyti'))->class('fw-bold mb-2');
foreach ($users as $user) {
    echo Html::div()->class('form-check')->open();
    echo Html::input('checkbox')->class('form-check-input')->name('assignedUsers[]')->value((string) $user->getId())->addAttributes(['checked' => true])->attribute('tabindex', ++$tabindex);
    echo Html::label($user->getUsername())->class('form-check-label');
    echo Html::div()->close();
}
echo Html::div()->close();

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.update_button', category: 'voyti'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
