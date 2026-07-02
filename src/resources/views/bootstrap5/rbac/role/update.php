<?php

declare(strict_types=1);

use YiiRocks\Voyti\Form\Rbac\RoleForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var RoleForm $model
 * @var array<string, list<string>> $errors
 * @var array $unassignedItems
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 * @var list<array{user: \YiiRocks\Voyti\Entity\User, assigned: bool}> $users
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.role.update_title', ['name' => $model->itemName], category: 'voyti'));

echo Html::div()->open();
include dirname(__DIR__, 2) . '/shared/_admin-menu.php';

echo Html::H1($translator->translate('voyti.view.role.update_title', ['name' => $model->itemName], category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/roles-update', ['name' => $model->itemName]))
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

echo Field::text($model, 'name');

echo Field::text($model, 'description');

echo Field::text($model, 'rule');

echo Html::h3($translator->translate('voyti.view.assignments.title', category: 'voyti'))->class('mb-3');

echo Html::div()->class('row g-3 mb-3')->open();
echo Html::div()->class('col-md-6')->open();
echo Html::div($translator->translate('voyti.view.assignments.assigned', category: 'voyti'))->class('fw-bold mb-2');
foreach ($users as $userData) {
    if ($userData['assigned']) {
        echo Html::div()->class('form-check')->open();
        echo Html::input('checkbox')->class('form-check-input')->name('assignedUsers[]')->value((string) $userData['user']->getId())->addAttributes(['checked' => true]);
        echo Html::label($userData['user']->getUsername())->class('form-check-label');
        echo Html::div()->close();
    }
}
echo Html::div()->close();
echo Html::div()->class('col-md-6')->open();
echo Html::div($translator->translate('voyti.view.assignments.available', category: 'voyti'))->class('fw-bold mb-2');
foreach ($users as $userData) {
    if (!$userData['assigned']) {
        echo Html::div()->class('form-check')->open();
        echo Html::input('checkbox')->class('form-check-input')->name('assignedUsers[]')->value((string) $userData['user']->getId());
        echo Html::label($userData['user']->getUsername())->class('form-check-label');
        echo Html::div()->close();
    }
}
echo Html::div()->close();
echo Html::div()->close();

echo Field::buttonGroup()
    ->buttons(
        Html::submitButton($translator->translate('voyti.view.update_button', category: 'voyti'))
    );

echo Html::form()->close();
echo Html::div()->close();
