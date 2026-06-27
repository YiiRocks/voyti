<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var array $errors
 * @var TranslatorInterface $translator
 * @var UrlGeneratorInterface $url
 * @var string $csrf
 */

$this->setTitle($translator->translate('voyti.view.two_factor_sms.title', category: 'voyti'));

echo Html::div()->class('voyti-two-factor-sms')->open();
Html::H3()->class('mb-3')->open();
echo $translator->translate('voyti.view.two_factor_sms.title', category: 'voyti');
echo Html::H3()->close();

if (!empty($errors)) {
    echo Html::div()->class('alert alert-danger')->open();
    foreach ($errors as $field => $fieldErrors) {
        foreach ((array) $fieldErrors as $error) {
            echo Html::div($error);
        }
    }
    echo Html::div()->close();
}

echo Html::form()
    ->post($url->generate('voyti/settings-two-factor-sms'))
    ->csrf($csrf)
    ->open();

echo Html::div()->class('mb-3')->open();
echo Html::label($translator->translate('voyti.view.two_factor_sms.phone', category: 'voyti'))->class('form-label');
echo Html::textInput('phone')->class('form-control')->required();
echo Html::div()->close();

echo Field::buttonGroup()
    ->buttons(
        Html::submitButton($translator->translate('voyti.view.two_factor_sms.send', category: 'voyti'))->class('btn', 'btn-primary')
    );

echo Html::form()->close();
echo Html::div()->close();
