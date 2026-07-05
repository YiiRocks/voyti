<?php

declare(strict_types=1);

use YiiRocks\Voyti\Form\Rbac\RuleForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var RuleForm $model
 * @var array<string, list<string>> $errors
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.rule.create_title', category: 'voyti'));

echo Html::div()->open();
include dirname(__DIR__, 2) . '/shared/_admin-menu.php';

echo Html::H1($translator->translate('voyti.view.rule.create_title', category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/rules-create'))
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

echo Field::text($model, 'class')->tabIndex(++$tabindex);

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.create_button', category: 'voyti'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
