<?php

declare(strict_types=1);

use YiiRocks\Voyti\Model\Form\Auth\LoginForm;
use YiiRocks\Voyti\ViewData\Session\LoginViewData;
use YiiRocks\Voyti\ViewData\Shared\FlashViewData;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\View\WebView;

/**
 * @var WebView $this
 * @var LoginForm $form
 * @var LoginViewData $data
 * @var TranslatorInterface $translator
 * @var FlashViewData $flash
 * @var string $csrf
 */

/** @psalm-suppress InvalidScope */
$this->setTitle($translator->translate('voyti.view.login.title'));

echo Html::div()->open();
/** @psalm-suppress InvalidScope */
echo $this->render('../shared/_flash', ['flash' => $flash]);
echo Html::H1($translator->translate('voyti.view.login.title'));

echo Html::form()
    ->post($data->formSubmitUrl)
    ->csrf($csrf)
    ->enctypeMultipartFormData()
    ->open();

echo Field::errorSummary($form);

$tabindex = 0;

echo Field::text($form, 'login')->tabIndex(++$tabindex)->autofocus();

echo Field::password($form, 'password')->tabIndex(++$tabindex);

echo Field::checkbox($form, 'rememberMe')->tabIndex(++$tabindex);

echo $data->recaptchaFieldHtml;

echo Field::buttonGroup()
    ->buttons(
        Html::resetButton($translator->translate('voyti.view.reset_button'))->attribute('tabindex', $tabindex + 2),
        Html::submitButton($translator->translate('voyti.view.login.sign_in_button'))->attribute('tabindex', ++$tabindex),
    );

echo Html::form()->close();

if ($data->connect->providers !== []) {
    echo Html::div()->class('mt-4 text-center')->open();
    /** @psalm-suppress InvalidScope */
    echo $this->render('../shared/_connect', ['connect' => $data->connect]);
    echo Html::div()->close();
}

echo Html::div()->class('mt-3')->open();

echo Html::a($translator->translate('voyti.view.login.forgot_password'), $data->forgotPasswordUrl);

if ($data->showRegisterLink) {
    echo ' | ';
    echo Html::a($translator->translate('voyti.view.login.register_link'), $data->registerUrl);
}
echo Html::div()->close();

echo Html::div()->close();
