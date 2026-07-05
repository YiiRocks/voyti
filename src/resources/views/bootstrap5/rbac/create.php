<?php

declare(strict_types=1);

use YiiRocks\Voyti\Form\Rbac\AbstractAuthItemForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var string $itemType 'role' or 'permission'
 * @var AbstractAuthItemForm $model
 * @var array<string, list<string>> $errors
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.' . $itemType . '.create_title', category: 'voyti'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_admin-menu', ['url' => $url, 'translator' => $translator]);

echo Html::H1($translator->translate('voyti.view.' . $itemType . '.create_title', category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/' . $itemType . 's-create'))
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

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.create_button', category: 'voyti'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
