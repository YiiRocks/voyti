<?php

declare(strict_types=1);

use YiiRocks\Voyti\AuthClient\AuthClientRegistry;
use YiiRocks\Voyti\Form\Auth\LoginForm;
use YiiRocks\Voyti\Helper\RecaptchaHelper;
use YiiRocks\Voyti\ModuleConfig;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var LoginForm $model
 * @var ModuleConfig $config
 * @var AuthClientRegistry $authClients
 * @var UrlGeneratorInterface $url
 * @var TranslatorInterface $translator
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.login.title', category: 'voyti'));

echo Html::div()->open();
echo Html::H1($translator->translate('voyti.view.login.title', category: 'voyti'));

/** @var string $csrf */
echo Html::form()
    ->post($url->generate('voyti/login'))
    ->csrf($csrf)
    ->enctypeMultipartFormData()
    ->open();

echo Field::errorSummary($model);

$tabindex = 0;

echo Field::text($model, 'login')->tabIndex(++$tabindex);

echo Field::password($model, 'password')->tabIndex(++$tabindex);

echo Field::checkbox($model, 'rememberMe')->tabIndex(++$tabindex);

echo RecaptchaHelper::render($model, $config);

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button', category: 'voyti'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.login.sign_in_button', category: 'voyti'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();

if ($authClients->all() !== []) {
    echo Html::div()->class('mt-4 text-center')->open();
    $routeName = 'voyti/auth';
    $excludedProviders = [];
    include dirname(__DIR__) . '/shared/_connect.php';
    echo Html::div()->close();
}

echo Html::div()->class('mt-3')->open();

echo Html::a($translator->translate('voyti.view.login.forgot_password', category: 'voyti'), $url->generate('voyti/forgot'));

if ($config->enableRegistration) {
    echo ' | ';
    echo Html::a($translator->translate('voyti.view.login.register_link', category: 'voyti'), $url->generate('voyti/register'));
}
echo Html::div()->close();

echo Html::div()->close();
