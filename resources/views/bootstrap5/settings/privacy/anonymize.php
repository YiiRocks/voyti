<?php

declare(strict_types=1);

use YiiRocks\Voyti\Form\Settings\AnonymizeForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var AnonymizeForm $model
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.anonymize.title', category: 'voyti'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.anonymize.title', category: 'voyti'));

echo Html::p()->class('alert alert-warning')->open();
echo $translator->translate('voyti.view.anonymize.warning', category: 'voyti');
echo Html::p()->close();

echo Html::form()
    ->post($url->generate('voyti/settings-privacy-anonymize'))
    ->csrf($csrf)
    ->open();

echo Field::errorSummary($model);

$tabindex = 0;

echo Field::password($model, 'password')->tabIndex(++$tabindex);

echo Field::checkbox($model, 'consent')->tabIndex(++$tabindex);

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.anonymize.button', category: 'voyti'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();
echo Html::div()->close();
