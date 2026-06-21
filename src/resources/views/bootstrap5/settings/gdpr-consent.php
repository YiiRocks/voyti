<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Settings\GdprDeleteForm $model
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

$this->setTitle($translator->translate('voyti.view.gdpr.consent_title', category: 'voyti'));

echo Html::div()->class('voyti-gdpr-consent')->open();
    Html::H1($translator->translate('voyti.view.gdpr.consent_title', category: 'voyti'));

    echo Html::form()
        ->post($url->generate('voyti/gdpr-consent'))
        ->csrf($csrf)
        ->open();

    echo Field::checkbox($model, 'consent');

    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.save_button', category: 'voyti'))
        );

    echo Html::form()->close();
echo Html::div()->close();
