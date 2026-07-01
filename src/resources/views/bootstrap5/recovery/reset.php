<?php

declare(strict_types=1);

use YiiRocks\Voyti\Form\Auth\RecoveryForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var RecoveryForm $model
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.recovery.reset_title', category: 'voyti'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.recovery.reset_title', category: 'voyti'));

echo Html::form()
    ->post()
    ->csrf($csrf)
    ->open();

echo Field::password($model, 'password');

echo Field::buttonGroup()
    ->buttons(
        Html::submitButton($translator->translate('voyti.view.recovery.reset_button', category: 'voyti'))
    );

echo Html::form()->close();
echo Html::div()->close();
