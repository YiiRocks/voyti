<?php

declare(strict_types=1);

use YiiRocks\Voyti\Form\Auth\RegistrationForm;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var RegistrationForm $model
 * @var ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.registration.register_title', category: 'voyti'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.registration.register_title', category: 'voyti'));

echo Html::form()
    ->post($url->generate('voyti/register'))
    ->csrf($csrf)
    ->open();

echo Field::errorSummary($model);

echo Field::text($model, 'username');

echo Field::email($model, 'email');

echo Field::password($model, 'password');

if ($config->enableGdprCompliance) {
    echo Field::checkbox($model, 'gdprConsent');
}

echo RecaptchaHelper::render($model, $config);

echo Field::buttonGroup()
    ->buttons(
        Html::submitButton($translator->translate('voyti.view.registration.register_button', category: 'voyti'))
    );

echo Html::div()->class('mt-3')->open();
echo Html::a($translator->translate('voyti.view.registration.already_have_account', category: 'voyti'), $url->generate('voyti/login'));
echo Html::div()->close();

echo Html::form()->close();
echo Html::div()->close();
