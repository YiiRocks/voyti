<?php

declare(strict_types=1);

use YiiRocks\Voyti\Helper\RecaptchaHelper;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * @var YiiRocks\Voyti\Form\Auth\LoginForm $model
 * @var YiiRocks\Voyti\ModuleConfig $config
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 * @var array $errors
 */

$this->setTitle($translator->translate('voyti.view.login.title', category: 'voyti'));

echo Html::div()->class('voyti-login')->open();
    echo Html::H1($translator->translate('voyti.view.login.title', category: 'voyti'));

    echo Html::form()
        ->post($url->generate('voyti/login'))
        ->csrf($csrf)
        ->enctypeMultipartFormData()
        ->open();

    echo Field::errorSummary(null)->errors($errors);

    echo Field::text($model, 'login');

    echo Field::password($model, 'password');

    echo Field::checkbox($model, 'rememberMe');

    echo RecaptchaHelper::render($model, $config);

    echo Field::buttonGroup()
        ->buttons(
            Html::submitButton($translator->translate('voyti.view.login.sign_in_button', category: 'voyti'))
        );

    echo Html::div()->class('mt-3')->open();
        echo Html::a($translator->translate('voyti.view.login.forgot_password', category: 'voyti'), $url->generate('voyti/forgot'));

        if ($config->enableRegistration) {
            echo Html::a($translator->translate('voyti.view.login.register_link', category: 'voyti'), $url->generate('voyti/register'));
        }
    echo Html::div()->close();

    echo Html::form()->close();
echo Html::div()->close();
