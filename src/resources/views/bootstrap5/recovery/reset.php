<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Auth\RecoveryForm $model
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

$this->setTitle($translator->translate('voyti.view.recovery.reset_title', category: 'voyti'));

echo Html::div()->class('voyti-reset')->open();
    Html::H1($translator->translate('voyti.view.recovery.reset_title', category: 'voyti'));

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
