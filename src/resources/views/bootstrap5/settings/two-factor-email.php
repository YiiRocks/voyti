<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var array<string, list<string>> $errors
 * @var TranslatorInterface $translator
 * @var UrlGeneratorInterface $url
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.two_factor_email.title', category: 'voyti'));

echo Html::div()->open();
Html::H3()->class('mb-3')->open();
echo $translator->translate('voyti.view.two_factor_email.title', category: 'voyti');
echo Html::H3()->close();

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

echo Html::p($translator->translate('voyti.view.two_factor_email.enter_code', category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/settings-two-factor-email'))
    ->csrf($csrf)
    ->open();

echo Html::div()->class('mb-3')->open();
echo Html::textInput('code')->class('form-control')->required();
echo Html::div()->close();

echo Field::buttonGroup()
    ->buttons(
        Html::submitButton($translator->translate('voyti.view.two_factor.verify', category: 'voyti'))->class('btn', 'btn-primary')
    );

echo Html::form()->close();
echo Html::div()->close();
