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
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.role.create_title', category: 'voyti'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.role.create_title', category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/roles-create'))
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

echo Field::buttonGroup()
    ->buttons(
        Html::submitButton($translator->translate('voyti.view.create_button', category: 'voyti'))
    );

echo Html::form()->close();
echo Html::div()->close();
